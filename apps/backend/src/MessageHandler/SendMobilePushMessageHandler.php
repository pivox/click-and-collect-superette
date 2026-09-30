<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\MobileDevice;
use App\Entity\Notification;
use App\Enum\MobileDeviceRevocationReason;
use App\Message\SendMobilePushMessage;
use App\Repository\MobileDeviceRepository;
use App\Repository\NotificationRepository;
use App\Service\Push\MobilePushEventCatalog;
use App\Service\Push\PushMessage;
use App\Service\Push\PushSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

/**
 * MOBILE-PUSH #620 lot C: async native push delivery.
 *
 * The in-app notification stays the persisted source of truth; this handler
 * only mirrors it to the device. A missing/revoked notification or device is
 * an acked silent skip (the state changed since dispatch, never an error).
 * Temporary provider failures throw a recoverable exception (Messenger
 * retry_strategy, final failure → `failed` transport); definitive
 * DeviceNotRegistered responses revoke the device without consuming a retry.
 *
 * Payload (§6, privacy): {type, notification_id, order_id, route} — opaque
 * identifiers only. Title/body reuse the persisted in-app labels; the Arabic
 * variant is selected when device.locale is `ar`, any other value falls back
 * to French (documented repli FR).
 */
#[AsMessageHandler]
final readonly class SendMobilePushMessageHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private MobileDeviceRepository $mobileDeviceRepository,
        private PushSenderInterface $pushSender,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.notification')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendMobilePushMessage $message): void
    {
        if (!Uuid::isValid($message->notificationId) || !Uuid::isValid($message->deviceId)) {
            $this->skip($message, 'invalid_uuid');

            return;
        }

        $notification = $this->notificationRepository->find($message->notificationId);
        if (!$notification instanceof Notification) {
            $this->skip($message, 'notification_not_found');

            return;
        }

        $device = $this->mobileDeviceRepository->find($message->deviceId);
        if (!$device instanceof MobileDevice) {
            $this->skip($message, 'device_not_found');

            return;
        }

        if (!$device->isActive()) {
            $this->skip($message, 'device_revoked_or_disabled');

            return;
        }

        $pushType = MobilePushEventCatalog::normalize($notification->getType());
        if (null === $pushType) {
            $this->skip($message, 'not_a_push_event');

            return;
        }

        $order = $notification->getOrder();
        if (null === $order) {
            $this->skip($message, 'notification_without_order');

            return;
        }

        $notificationId = $notification->getId()->toRfc4122();
        $orderId = $order->getId()->toRfc4122();
        $isArabic = 'ar' === $device->getLocale();

        $pushMessage = new PushMessage(
            title: $isArabic ? $notification->getTitleAr() : $notification->getTitleFr(),
            body: $isArabic ? $notification->getBodyAr() : $notification->getBodyFr(),
            data: [
                'type' => $pushType,
                'notification_id' => $notificationId,
                'order_id' => $orderId,
                'route' => MobilePushEventCatalog::routeFor($pushType, $orderId),
            ],
            collapseId: $notificationId,
        );

        $result = $this->pushSender->send($pushMessage, $device);

        if ($result->isSuccess()) {
            $device->recordSendSuccess();
            $this->entityManager->flush();
            $this->logger->info('notification.mobile_push_sent', [
                'type' => $pushType,
                'notification_id' => $notificationId,
                'device_id' => $message->deviceId,
            ]);

            return;
        }

        $device->recordSendFailure();

        if ($result->isTemporaryFailure()) {
            $this->entityManager->flush();
            $this->logger->warning('notification.mobile_push_temporary_failure', [
                'notification_id' => $notificationId,
                'device_id' => $message->deviceId,
                'reason' => $result->reason,
            ]);

            // Retriable: Messenger retry_strategy (3 attempts, backoff x2),
            // final failure lands in the `failed` transport (#353).
            throw new RecoverableMessageHandlingException(\sprintf('Temporary push failure: %s', (string) $result->reason));
        }

        // Permanent failure — never retried. Only a definitive provider
        // rejection of the token revokes the device (decision 6).
        if ($result->deviceUnregistered) {
            $device->revoke(MobileDeviceRevocationReason::ProviderRejected);
        }
        $this->entityManager->flush();
        $this->logger->warning('notification.mobile_push_permanent_failure', [
            'notification_id' => $notificationId,
            'device_id' => $message->deviceId,
            'reason' => $result->reason,
            'device_revoked' => $result->deviceUnregistered,
        ]);
    }

    private function skip(SendMobilePushMessage $message, string $reason): void
    {
        $this->logger->info('notification.mobile_push_skipped', [
            'notification_id' => $message->notificationId,
            'device_id' => $message->deviceId,
            'reason' => $reason,
        ]);
    }
}
