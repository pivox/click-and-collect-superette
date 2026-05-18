<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Order;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotificationService implements PickupReminderNotifierInterface
{
    public const TYPE_PICKUP_REMINDER = 'pickup_reminder';
    public const TYPE_MERCHANT_RESPONSE_TIMEOUT = 'merchant_response_timeout';
    public const TYPE_PARTIAL_ACCEPTANCE_REMINDER = 'partial_acceptance_reminder';
    public const TYPE_PARTIAL_ACCEPTANCE_TIMEOUT = 'partial_acceptance_timeout';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
    ) {
    }

    public function notifyCustomerOrderAccepted(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia acceptée',
            'تم قبول القاضية',
            'Votre commande a été acceptée par la supérette.',
            'تم قبول طلبكم من طرف العطار.',
        );
    }

    public function notifyCustomerOrderRejected(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia refusée',
            'تم رفض القاضية',
            'Votre commande a été refusée par la supérette.',
            'تم رفض طلبكم من طرف العطار.',
        );
    }

    public function notifyCustomerOrderPartiallyAccepted(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia partiellement acceptée',
            'تم قبول جزء من القاضية',
            'Certains produits ne sont pas disponibles. Merci de vérifier votre Kadhia.',
            'بعض المنتجات غير متوفرة. يرجى مراجعة القاضية.',
        );
    }

    public function notifyCustomerOrderPreparing(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia en préparation',
            'القاضية في التحضير',
            'Votre commande est en cours de préparation.',
            'طلبكم في طور التحضير.',
        );
    }

    public function notifyCustomerOrderReady(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia prête',
            'القاضية واجدة',
            'Votre commande est prête à être retirée. Présentez votre QR code en supérette.',
            'طلبكم واجد للاستلام. أظهروا رمز QR في العطار.',
        );
    }

    public function notifyCustomerOrderCompleted(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia retirée',
            'تم استلام القاضية',
            'Votre commande a été retirée avec succès.',
            'تم استلام طلبكم بنجاح.',
        );
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

        $this->persistForCustomer(
            $order,
            'Rappel de retrait',
            'تذكير بالاستلام',
            $bodyFr,
            $bodyAr,
            self::TYPE_PICKUP_REMINDER,
        );
    }

    public function notifyCustomerMerchantResponseTimeout(Order $order): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, self::TYPE_MERCHANT_RESPONSE_TIMEOUT)) {
            return;
        }

        $this->persistForCustomer(
            $order,
            'Commande annulée automatiquement',
            'تم إلغاء الطلب آليًا',
            'Votre Kadhia a été annulée car le marchand n’a pas répondu à temps.',
            'تم إلغاء القاضية لأن التاجر لم يرد في الوقت المناسب.',
            self::TYPE_MERCHANT_RESPONSE_TIMEOUT,
        );
    }

    public function notifyCustomerPartialAcceptanceReminder(Order $order, string $cycleType): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, $cycleType)) {
            return;
        }

        $this->persistForCustomer(
            $order,
            'Réponse nécessaire',
            'يلزم الرد',
            'Votre Kadhia a été acceptée partiellement. Confirmez vos modifications avant l’expiration du délai.',
            'تم قبول القاضية جزئياً. أكدوا التعديلات قبل انتهاء المهلة.',
            $cycleType,
        );
    }

    public function notifyCustomerPartialAcceptanceTimeout(Order $order): void
    {
        if ($this->notificationRepository->existsForOrderAndType($order, self::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT)) {
            return;
        }

        $this->persistForCustomer(
            $order,
            'Commande annulée automatiquement',
            'تم إلغاء الطلب آليًا',
            'Votre Kadhia a été annulée car l’acceptation partielle n’a pas été confirmée à temps.',
            'تم إلغاء القاضية لأن القبول الجزئي لم يتم تأكيده في الوقت المناسب.',
            self::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT,
        );
    }

    public function notifyMerchantOrderSubmitted(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Nouvelle commande',
            'طلب جديد',
            'Une nouvelle Kadhia a été soumise.',
            'تم إرسال قاضية جديدة.',
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
        );
    }

    private function persistForCustomer(
        Order $order,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
        ?string $type = null,
    ): void {
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
    }

    private function persistForMerchant(
        Order $order,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
    ): void {
        $owner = $order->getShop()->getOwner();
        if (null === $owner) {
            return;
        }
        $notification = new Notification(
            user: $owner,
            titleFr: $titleFr,
            titleAr: $titleAr,
            bodyFr: $bodyFr,
            bodyAr: $bodyAr,
            order: $order,
        );
        $this->entityManager->persist($notification);
    }
}
