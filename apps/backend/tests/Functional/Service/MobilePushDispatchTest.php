<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\MobileDevice;
use App\Entity\User;
use App\Enum\MobileApplication;
use App\Enum\MobileDeviceRevocationReason;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use App\Message\SendMobilePushMessage;
use App\Service\NotificationService;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use App\Tests\Functional\OrderPickupFixtureTrait;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * MOBILE-PUSH #620 lot D integration: a V1 notification persisted by
 * NotificationService dispatches one SendMobilePushMessage per ACTIVE device
 * of the recipient, on the async transport (in-memory in test).
 */
final class MobilePushDispatchTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    public function testOrderAcceptedDispatchesOnlyToActiveClientDevices(): void
    {
        $customer = $this->createUser('dispatch-customer@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('dispatch-merchant@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $order = $this->createSubmittedOrder($customer, $shop, $this->createMerchantProduct($shop));

        $activeDevice = $this->createDevice($customer, 'active');
        $revokedDevice = $this->createDevice($customer, 'revoked');
        $revokedDevice->revoke(MobileDeviceRevocationReason::Logout);
        $disabledDevice = $this->createDevice($customer, 'disabled');
        $disabledDevice->setEnabled(false);
        // Decision 8: a merchant device of another user never receives a
        // client event.
        $this->createDevice($merchant, 'merchant', MobileApplication::Merchant);
        $this->entityManager->flush();

        $this->notificationService()->notifyCustomerOrderAccepted($order);
        $this->entityManager->flush();

        $messages = $this->sentPushMessages();
        self::assertCount(1, $messages);
        self::assertSame($activeDevice->getId()->toRfc4122(), $messages[0]->deviceId);
    }

    public function testNonV1OrderPreparingDispatchesNothing(): void
    {
        $customer = $this->createUser('dispatch-preparing@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('dispatch-preparing-merchant@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $order = $this->createSubmittedOrder($customer, $shop, $this->createMerchantProduct($shop));
        $this->createDevice($customer, 'active');

        $this->notificationService()->notifyCustomerOrderPreparing($order);
        $this->entityManager->flush();

        self::assertCount(0, $this->sentPushMessages());
        // The in-app notification remains the persisted source of truth.
        $notifications = $this->entityManager->getRepository(\App\Entity\Notification::class)->findBy(['type' => NotificationService::TYPE_ORDER_PREPARING]);
        self::assertCount(1, $notifications);
    }

    public function testMerchantOrderSubmittedDispatchesToMerchantDevices(): void
    {
        $customer = $this->createUser('dispatch-submit-customer@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('dispatch-submit-merchant@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $order = $this->createSubmittedOrder($customer, $shop, $this->createMerchantProduct($shop));

        $merchantDevice = $this->createDevice($merchant, 'merchant', MobileApplication::Merchant);
        // The customer's client device must never receive a merchant event.
        $this->createDevice($customer, 'client');

        $this->notificationService()->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();

        $messages = $this->sentPushMessages();
        self::assertCount(1, $messages);
        self::assertSame($merchantDevice->getId()->toRfc4122(), $messages[0]->deviceId);
    }

    private function notificationService(): NotificationService
    {
        $service = self::getContainer()->get(NotificationService::class);
        self::assertInstanceOf(NotificationService::class, $service);

        return $service;
    }

    /**
     * @return list<SendMobilePushMessage>
     */
    private function sentPushMessages(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendMobilePushMessage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    private function createDevice(User $user, string $tokenSuffix, MobileApplication $application = MobileApplication::Client): MobileDevice
    {
        $device = new MobileDevice(
            user: $user,
            application: $application,
            platform: MobilePlatform::Android,
            provider: MobilePushProvider::Expo,
            pushToken: 'ExponentPushToken['.$user->getId()->toRfc4122().'-'.$tokenSuffix.']',
        );
        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $device;
    }
}
