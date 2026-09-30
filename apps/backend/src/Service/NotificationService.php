<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Order;
use App\Repository\NotificationRepository;
use App\Service\Push\MobilePushDispatcher;
use App\Service\Push\MobilePushEventCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class NotificationService implements PickupReminderNotifierInterface
{
    // MOBILE-PUSH #620 lot A: stable customer order-cycle types (previously
    // persisted with a null type). Existing rows keep their null type — no
    // data migration; the API already exposes the nullable `type` field.
    public const TYPE_ORDER_ACCEPTED = 'order_accepted';
    public const TYPE_ORDER_PARTIALLY_ACCEPTED = 'order_partially_accepted';
    public const TYPE_ORDER_REJECTED = 'order_rejected';
    public const TYPE_ORDER_PREPARING = 'order_preparing';
    public const TYPE_ORDER_READY = 'order_ready';
    public const TYPE_ORDER_COMPLETED = 'order_completed';

    /**
     * #620 lot A: cycle types are intentionally NOT deduplicated by
     * (order, type) — an order only repeats a status after a
     * partial-acceptance re-submission, where a second notification is
     * wanted. Because the table carries the UNIQ_NOTIFICATIONS_ORDER_TYPE_USER
     * constraint, a repeated cycle event gets a per-occurrence type variant
     * (same motif as partial_acceptance_reminder_{cycleId}); push dispatch
     * normalizes variants back to the stable base type.
     *
     * @var list<string>
     */
    private const CUSTOMER_CYCLE_TYPES = [
        self::TYPE_ORDER_ACCEPTED,
        self::TYPE_ORDER_PARTIALLY_ACCEPTED,
        self::TYPE_ORDER_REJECTED,
        self::TYPE_ORDER_PREPARING,
        self::TYPE_ORDER_READY,
        self::TYPE_ORDER_COMPLETED,
    ];

    public const TYPE_PICKUP_REMINDER = 'pickup_reminder';
    public const TYPE_MERCHANT_RESPONSE_TIMEOUT = 'merchant_response_timeout';
    public const TYPE_PARTIAL_ACCEPTANCE_REMINDER = 'partial_acceptance_reminder';
    public const TYPE_PARTIAL_ACCEPTANCE_TIMEOUT = 'partial_acceptance_timeout';
    // MERCHANT-TEAM-005: stable merchant event types (previously null).
    public const TYPE_MERCHANT_ORDER_SUBMITTED = 'merchant_order_submitted';
    public const TYPE_MERCHANT_ORDER_CANCELLED = 'merchant_order_cancelled';
    public const TYPE_MERCHANT_PICKUP_COMPLETED = 'merchant_pickup_completed';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        #[Autowire(service: 'monolog.logger.notification')]
        private LoggerInterface $logger,
        private ?WebPushService $webPushService = null,
        private ?MerchantNotificationRecipientResolver $merchantRecipientResolver = null,
        private ?MobilePushDispatcher $mobilePushDispatcher = null,
    ) {
    }

    public function notifyCustomerOrderAccepted(Order $order): void
    {
        $notification = $this->persistForCustomer(
            $order,
            'Kadhia acceptée',
            'تم قبول القاضية',
            'Votre commande a été acceptée par la supérette.',
            'تم قبول طلبكم من طرف العطار.',
            self::TYPE_ORDER_ACCEPTED,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyCustomerOrderRejected(Order $order): void
    {
        $notification = $this->persistForCustomer(
            $order,
            'Kadhia refusée',
            'تم رفض القاضية',
            'Votre commande a été refusée par la supérette.',
            'تم رفض طلبكم من طرف العطار.',
            self::TYPE_ORDER_REJECTED,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyCustomerOrderPartiallyAccepted(Order $order): void
    {
        $notification = $this->persistForCustomer(
            $order,
            'Kadhia partiellement acceptée',
            'تم قبول جزء من القاضية',
            'Certains produits ne sont pas disponibles. Merci de vérifier votre Kadhia.',
            'بعض المنتجات غير متوفرة. يرجى مراجعة القاضية.',
            self::TYPE_ORDER_PARTIALLY_ACCEPTED,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyCustomerOrderPreparing(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia en préparation',
            'القاضية في التحضير',
            'Votre commande est en cours de préparation.',
            'طلبكم في طور التحضير.',
            self::TYPE_ORDER_PREPARING,
        );
    }

    public function notifyCustomerOrderReady(Order $order): void
    {
        $notification = $this->persistForCustomer(
            $order,
            'Kadhia prête',
            'القاضية واجدة',
            'Votre commande est prête à être retirée. Présentez votre QR code en supérette.',
            'طلبكم واجد للاستلام. أظهروا رمز QR في العطار.',
            self::TYPE_ORDER_READY,
        );
        $this->dispatchMobilePush($notification);

        // best-effort push notification
        if (null !== $this->webPushService) {
            try {
                $this->webPushService->sendToUser(
                    $order->getCustomer(),
                    'Kadhia prête',
                    'Votre commande est prête à être retirée. Présentez votre QR code en supérette.',
                    '/orders/'.$order->getId()->toRfc4122(),
                );
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $this->logger->warning('push_send_failed_ready', ['error' => $e->getMessage()]);
            }
        }
    }

    public function notifyCustomerOrderCompleted(Order $order): void
    {
        $notification = $this->persistForCustomer(
            $order,
            'Kadhia retirée',
            'تم استلام القاضية',
            'Votre commande a été retirée avec succès.',
            'تم استلام طلبكم بنجاح.',
            self::TYPE_ORDER_COMPLETED,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyCustomerPickupReminder(Order $order): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, self::TYPE_PICKUP_REMINDER)) {
            return;
        }

        $slot = $order->getPickupSlot();
        $shopName = $order->getShop()->getName();

        if (null !== $slot) {
            $slotTime = $slot->getStartsAt()
                ->setTimezone(new \DateTimeZone('Africa/Tunis'))
                ->format('H\hi');
            $bodyFr = \sprintf(
                'Votre Kadhia chez %s est prête. Votre créneau est à %s. Présentez votre QR code en supérette.',
                $shopName,
                $slotTime,
            );
            $bodyAr = \sprintf(
                'قاضيتك في %s واجدة. موعد استلامها %s. أظهر رمز QR في العطار.',
                $shopName,
                $slotTime,
            );
        } else {
            $bodyFr = \sprintf('Votre Kadhia chez %s est prête. Pensez à la retirer pendant votre créneau.', $shopName);
            $bodyAr = \sprintf('قاضيتك في %s واجدة. تذكروا استلامها خلال الموعد المحدد.', $shopName);
        }

        $notification = $this->persistForCustomer(
            $order,
            'Rappel de retrait',
            'تذكير بالاستلام',
            $bodyFr,
            $bodyAr,
            self::TYPE_PICKUP_REMINDER,
        );
        $this->dispatchMobilePush($notification);

        // best-effort push notification
        if (null !== $this->webPushService) {
            try {
                $this->webPushService->sendToUser(
                    $order->getCustomer(),
                    'Rappel de retrait',
                    $bodyFr,
                    '/orders/'.$order->getId()->toRfc4122(),
                );
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $this->logger->warning('push_send_failed_reminder', ['error' => $e->getMessage()]);
            }
        }
    }

    public function notifyCustomerMerchantResponseTimeout(Order $order): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, self::TYPE_MERCHANT_RESPONSE_TIMEOUT)) {
            return;
        }

        $notification = $this->persistForCustomer(
            $order,
            'Commande annulée automatiquement',
            'تم إلغاء الطلب آليًا',
            'Votre Kadhia a été annulée car le marchand n’a pas répondu à temps.',
            'تم إلغاء القاضية لأن التاجر لم يرد في الوقت المناسب.',
            self::TYPE_MERCHANT_RESPONSE_TIMEOUT,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyCustomerPartialAcceptanceReminder(Order $order, string $cycleType): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, $cycleType)) {
            return;
        }

        $notification = $this->persistForCustomer(
            $order,
            'Réponse nécessaire',
            'يلزم الرد',
            'Votre Kadhia a été acceptée partiellement. Confirmez vos modifications avant l’expiration du délai.',
            'تم قبول القاضية جزئياً. أكدوا التعديلات قبل انتهاء المهلة.',
            $cycleType,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyCustomerPartialAcceptanceTimeout(Order $order): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, self::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT)) {
            return;
        }

        $notification = $this->persistForCustomer(
            $order,
            'Commande annulée automatiquement',
            'تم إلغاء الطلب آليًا',
            'Votre Kadhia a été annulée car l’acceptation partielle n’a pas été confirmée à temps.',
            'تم إلغاء القاضية لأن القبول الجزئي لم يتم تأكيده في الوقت المناسب.',
            self::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT,
        );
        $this->dispatchMobilePush($notification);
    }

    public function notifyMerchantOrderSubmitted(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Nouvelle commande',
            'طلب جديد',
            'Une nouvelle Kadhia a été soumise.',
            'تم إرسال قاضية جديدة.',
            self::TYPE_MERCHANT_ORDER_SUBMITTED,
            pushUrl: '/merchant/orders/'.$order->getId()->toRfc4122(),
        );
    }

    public function notifyMerchantOrderCancelled(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Commande annulée',
            'تم إلغاء الطلب',
            'Le client a annulé sa commande.',
            'قام الحريف بإلغاء الطلب.',
            self::TYPE_MERCHANT_ORDER_CANCELLED,
        );
    }

    public function notifyMerchantPickupCompleted(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Retrait finalisé',
            'تم إتمام الاستلام',
            'Le retrait de la commande est finalisé.',
            'تم إتمام استلام الطلب.',
            self::TYPE_MERCHANT_PICKUP_COMPLETED,
        );
    }

    private function persistForCustomer(
        Order $order,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
        ?string $type = null,
    ): Notification {
        $orderId = $order->getId()->toRfc4122();

        // #620 lot A: a repeated cycle event (only possible after a
        // partial-acceptance re-submission) must produce a SECOND in-app
        // notification. The unique constraint on (order, type, user) forbids
        // an identical type, so the repeat gets a per-occurrence variant —
        // same motif as partial_acceptance_reminder_{cycleId}.
        if (null !== $type
            && \in_array($type, self::CUSTOMER_CYCLE_TYPES, true)
            && $this->notificationRepository->existsForOrderAndType($order, $type)) {
            $type .= '_'.Uuid::v4()->toRfc4122();
        }
        $this->logger->debug('notification.attempt', [
            'type' => $type ?? 'generic',
            'order_id' => $orderId,
            'recipient' => 'customer',
        ]);

        try {
            $notification = new Notification(
                user: $order->getCustomer(),
                titleFr: $titleFr,
                titleAr: $titleAr,
                bodyFr: $bodyFr,
                bodyAr: $bodyAr,
                order: $order,
                type: $type,
            );
            $this->entityManager->persist($notification);
            $this->logger->info('notification.persisted', [
                'type' => $type ?? 'generic',
                'order_id' => $orderId,
                'recipient' => 'customer',
            ]);

            return $notification;
        } catch (\Throwable $e) {
            $this->logger->error('notification.failed', [
                'type' => $type ?? 'generic',
                'order_id' => $orderId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * MERCHANT-TEAM-005: one persisted notification per eligible account of
     * the shop organization (each with its own read state), idempotent per
     * account on retries, with best-effort isolated Web Push per recipient.
     */
    private function persistForMerchant(
        Order $order,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
        string $type,
        ?string $pushUrl = null,
    ): void {
        $orderId = $order->getId()->toRfc4122();
        $shop = $order->getShop();

        $recipients = null !== $this->merchantRecipientResolver
            ? $this->merchantRecipientResolver->resolveForShop($shop)
            : array_values(array_filter([$shop->getOwner()], static fn ($owner): bool => null !== $owner && $owner->isActive()));

        $this->logger->info('notification.merchant_recipients_resolved', [
            'type' => $type,
            'order_id' => $orderId,
            'store_id' => $shop->getId()->toRfc4122(),
            'organization_id' => $shop->getMerchantOrganization()?->getId()->toRfc4122(),
            'recipient_count' => \count($recipients),
        ]);

        if ([] === $recipients) {
            $this->logger->warning('notification.no_owner', [
                'order_id' => $orderId,
                'store_id' => $shop->getId()->toRfc4122(),
            ]);

            return;
        }

        /** @var list<Notification> $createdNotifications */
        $createdNotifications = [];

        foreach ($recipients as $recipient) {
            // Retry idempotence per account (also enforced by the unique
            // constraint on order/type/user).
            if ($this->notificationRepository->existsForOrderTypeAndUser($order, $type, $recipient)) {
                continue;
            }

            try {
                $notification = new Notification(
                    user: $recipient,
                    titleFr: $titleFr,
                    titleAr: $titleAr,
                    bodyFr: $bodyFr,
                    bodyAr: $bodyAr,
                    order: $order,
                    type: $type,
                );
                $this->entityManager->persist($notification);
                $createdNotifications[] = $notification;
                $this->logger->info('notification.persisted', [
                    'type' => $type,
                    'order_id' => $orderId,
                    'recipient' => 'merchant',
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('notification.failed', [
                    'type' => $type,
                    'order_id' => $orderId,
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        // MOBILE-PUSH #620 lot D: one dispatch per recipient notification —
        // each eligible merchant account (MERCHANT-TEAM-005 resolver) fans out
        // to its own active devices. Best-effort, never blocking.
        foreach ($createdNotifications as $createdNotification) {
            $this->dispatchMobilePush($createdNotification);
        }

        if (null === $pushUrl || null === $this->webPushService) {
            return;
        }

        // Best-effort push, isolated per recipient: one failure never blocks
        // the order transition or the other recipients.
        foreach ($recipients as $recipient) {
            try {
                $this->webPushService->sendToUser($recipient, $titleFr, $bodyFr, $pushUrl);
                $this->entityManager->flush();
                $this->logger->info('notification.push_dispatch_succeeded', [
                    'type' => $type,
                    'order_id' => $orderId,
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('notification.push_dispatch_failed', [
                    'type' => $type,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * MOBILE-PUSH #620 lot D: fans a persisted V1 notification out to the
     * recipient's active mobile devices, AFTER a successful flush of the
     * notification row (best-effort + second flush, PR #232). Types outside
     * the V1 catalog emit nothing; any failure is logged and never interrupts
     * the business transition.
     */
    private function dispatchMobilePush(Notification $notification): void
    {
        if (null === $this->mobilePushDispatcher || !MobilePushEventCatalog::isPushEvent($notification->getType())) {
            return;
        }

        try {
            // The async handler reloads the notification by id: its row must
            // be flushed before the message is emitted.
            $this->entityManager->flush();
            $this->mobilePushDispatcher->dispatchForNotification($notification);
        } catch (\Throwable $e) {
            $this->logger->warning('notification.mobile_push_dispatch_failed', [
                'type' => $notification->getType(),
                'notification_id' => $notification->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
