<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\MobileDevice;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\MobileApplication;
use App\Enum\MobileDeviceRevocationReason;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use App\Message\SendMobilePushMessage;
use App\MessageHandler\SendMobilePushMessageHandler;
use App\Repository\MobileDeviceRepository;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Service\Push\PushMessage;
use App\Service\Push\PushSenderInterface;
use App\Service\Push\PushSendResult;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class SendMobilePushMessageHandlerTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    /** @var PushSenderInterface&MockObject */
    private PushSenderInterface $pushSender;

    protected function setUp(): void
    {
        parent::setUp();

        // Pattern #9: mock the interface, never the final adapter — no test
        // ever performs a network call.
        $this->pushSender = $this->createMock(PushSenderInterface::class);
    }

    public function testSendsMinimalLocalizedPayloadToActiveDevice(): void
    {
        [$notification, $device] = $this->createNotificationAndDevice(locale: 'ar');
        $order = $notification->getOrder();
        self::assertNotNull($order);
        $orderId = $order->getId()->toRfc4122();
        $notificationId = $notification->getId()->toRfc4122();

        $this->pushSender
            ->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (PushMessage $message, MobileDevice $target) use ($notification, $notificationId, $orderId, $device): PushSendResult {
                self::assertTrue($target->getId()->equals($device->getId()));
                // AR localization from the persisted in-app labels.
                self::assertSame($notification->getTitleAr(), $message->title);
                self::assertSame($notification->getBodyAr(), $message->body);
                // Privacy §6: strictly minimal payload, opaque identifiers
                // only — no Kadhia content, amount, contact, note or token.
                self::assertSame([
                    'type' => 'order_accepted',
                    'notification_id' => $notificationId,
                    'order_id' => $orderId,
                    'route' => '/orders/'.$orderId,
                ], $message->data);
                self::assertSame($notificationId, $message->collapseId);

                return PushSendResult::success();
            });

        $this->createHandler()(new SendMobilePushMessage($notificationId, $device->getId()->toRfc4122()));

        $this->entityManager->refresh($device);
        self::assertNotNull($device->getLastSuccessAt());
    }

    public function testSkipsRevokedDeviceWithoutCallingSender(): void
    {
        [$notification, $device] = $this->createNotificationAndDevice();
        $device->revoke(MobileDeviceRevocationReason::Logout);
        $this->entityManager->flush();

        $this->pushSender->expects(self::never())->method('send');

        $this->createHandler()(new SendMobilePushMessage(
            $notification->getId()->toRfc4122(),
            $device->getId()->toRfc4122(),
        ));
    }

    public function testSkipsDisabledDeviceWithoutCallingSender(): void
    {
        [$notification, $device] = $this->createNotificationAndDevice();
        $device->setEnabled(false);
        $this->entityManager->flush();

        $this->pushSender->expects(self::never())->method('send');

        $this->createHandler()(new SendMobilePushMessage(
            $notification->getId()->toRfc4122(),
            $device->getId()->toRfc4122(),
        ));
    }

    public function testSkipsWhenNotificationDisappeared(): void
    {
        [, $device] = $this->createNotificationAndDevice();

        $this->pushSender->expects(self::never())->method('send');

        // Pattern #27: real non-existing UUID v4 — acked silent skip, no error.
        $this->createHandler()(new SendMobilePushMessage(
            '550e8400-e29b-41d4-a716-446655440000',
            $device->getId()->toRfc4122(),
        ));
    }

    public function testTemporaryFailureThrowsRecoverableException(): void
    {
        [$notification, $device] = $this->createNotificationAndDevice();

        $this->pushSender->expects(self::once())->method('send')
            ->willReturn(PushSendResult::temporaryFailure('http_503'));

        $handler = $this->createHandler();

        try {
            $handler(new SendMobilePushMessage(
                $notification->getId()->toRfc4122(),
                $device->getId()->toRfc4122(),
            ));
            self::fail('Expected a RecoverableMessageHandlingException');
        } catch (RecoverableMessageHandlingException) {
            // Retriable by the Messenger retry_strategy.
        }

        $this->entityManager->refresh($device);
        self::assertSame(1, $device->getFailureCount());
        self::assertNotNull($device->getLastFailureAt());
        self::assertNull($device->getRevokedAt());
    }

    public function testDeviceNotRegisteredRevokesDeviceWithoutRetry(): void
    {
        [$notification, $device] = $this->createNotificationAndDevice();

        $this->pushSender->expects(self::once())->method('send')->willReturn(
            PushSendResult::permanentFailure('DeviceNotRegistered', deviceUnregistered: true),
        );

        // No exception: a definitive rejection must not consume a retry.
        $this->createHandler()(new SendMobilePushMessage(
            $notification->getId()->toRfc4122(),
            $device->getId()->toRfc4122(),
        ));

        $this->entityManager->refresh($device);
        self::assertNotNull($device->getRevokedAt());
        self::assertSame(MobileDeviceRevocationReason::ProviderRejected, $device->getRevocationReason());
    }

    public function testNonV1TypeIsSkipped(): void
    {
        [$notification, $device] = $this->createNotificationAndDevice(type: NotificationService::TYPE_ORDER_PREPARING);

        $this->pushSender->expects(self::never())->method('send');

        $this->createHandler()(new SendMobilePushMessage(
            $notification->getId()->toRfc4122(),
            $device->getId()->toRfc4122(),
        ));
    }

    private function createHandler(): SendMobilePushMessageHandler
    {
        $notificationRepository = $this->entityManager->getRepository(Notification::class);
        self::assertInstanceOf(NotificationRepository::class, $notificationRepository);
        $deviceRepository = $this->entityManager->getRepository(MobileDevice::class);
        self::assertInstanceOf(MobileDeviceRepository::class, $deviceRepository);

        return new SendMobilePushMessageHandler(
            $notificationRepository,
            $deviceRepository,
            $this->pushSender,
            $this->entityManager,
            new NullLogger(),
        );
    }

    /**
     * @return array{Notification, MobileDevice}
     */
    private function createNotificationAndDevice(
        string $locale = 'fr',
        string $type = NotificationService::TYPE_ORDER_ACCEPTED,
    ): array {
        $customer = $this->createUser('push-handler-customer-'.$locale.'-'.$type.'@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('push-handler-merchant-'.$locale.'-'.$type.'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $order = $this->createSubmittedOrder($customer, $shop, $this->createMerchantProduct($shop));

        $notification = new Notification(
            user: $customer,
            titleFr: 'Kadhia acceptée',
            titleAr: 'تم قبول القاضية',
            bodyFr: 'Votre commande a été acceptée par la supérette.',
            bodyAr: 'تم قبول طلبكم من طرف العطار.',
            order: $order,
            type: $type,
        );
        $this->entityManager->persist($notification);

        $device = $this->createDevice($customer, $locale);

        return [$notification, $device];
    }

    private function createDevice(User $user, string $locale = 'fr'): MobileDevice
    {
        $device = new MobileDevice(
            user: $user,
            application: MobileApplication::Client,
            platform: MobilePlatform::Android,
            provider: MobilePushProvider::Expo,
            pushToken: 'ExponentPushToken['.$user->getId()->toRfc4122().']',
            locale: $locale,
        );
        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $device;
    }
}
