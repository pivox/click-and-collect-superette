<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\Notification;
use App\Message\SendMobilePushMessage;
use App\Repository\MobileDeviceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * MOBILE-PUSH #620 lot D: fans a persisted V1 notification out to one async
 * SendMobilePushMessage per active device of its recipient, filtered by
 * application (decision 8 — a merchant push can never reach a client app).
 *
 * Called by NotificationService AFTER the notification flush, inside its
 * best-effort try-catch (PR #232): a dispatch failure is logged and never
 * blocks a business transition.
 */
final readonly class MobilePushDispatcher
{
    public function __construct(
        private MobileDeviceRepository $mobileDeviceRepository,
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'monolog.logger.notification')]
        private LoggerInterface $logger,
    ) {
    }

    public function dispatchForNotification(Notification $notification): void
    {
        $pushType = MobilePushEventCatalog::normalize($notification->getType());
        if (null === $pushType) {
            // Not a V1 push event (e.g. order_preparing) — in-app only.
            return;
        }

        $application = MobilePushEventCatalog::applicationFor($pushType);
        $devices = $this->mobileDeviceRepository->findActiveForUserAndApplication($notification->getUser(), $application);
        if ([] === $devices) {
            return;
        }

        $notificationId = $notification->getId()->toRfc4122();
        foreach ($devices as $device) {
            $this->messageBus->dispatch(new SendMobilePushMessage(
                notificationId: $notificationId,
                deviceId: $device->getId()->toRfc4122(),
            ));
        }

        $this->logger->info('notification.mobile_push_dispatched', [
            'type' => $pushType,
            'notification_id' => $notificationId,
            'device_count' => \count($devices),
        ]);
    }
}
