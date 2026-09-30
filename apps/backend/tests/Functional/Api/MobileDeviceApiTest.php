<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MobileDevice;
use App\Enum\MobileApplication;
use App\Enum\MobileDeviceRevocationReason;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use App\Repository\MobileDeviceRepository;

final class MobileDeviceApiTest extends FunctionalApiTestCase
{
    private const EXPO_TOKEN = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';

    public function testRegisterCreatesClientDeviceForCustomer(): void
    {
        $customer = $this->createUser('device-customer@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/mobile/devices', [
            'push_token' => self::EXPO_TOKEN,
            'application' => 'client',
            'platform' => 'android',
            'locale' => 'ar',
            'timezone' => 'Africa/Tunis',
            'app_version' => '1.2.0',
            'os_major_version' => '14',
        ], $customer);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('client', $payload['application']);
        self::assertSame('android', $payload['platform']);
        self::assertSame('expo', $payload['provider']);
        self::assertSame('ar', $payload['locale']);
        self::assertSame('Africa/Tunis', $payload['timezone']);
        self::assertSame('1.2.0', $payload['app_version']);
        self::assertSame('14', $payload['os_major_version']);
        self::assertTrue($payload['enabled']);
        self::assertArrayNotHasKey('push_token', $payload);

        $device = $this->deviceRepository()->findOneByTokenHash(hash('sha256', self::EXPO_TOKEN));
        self::assertNotNull($device);
        self::assertTrue($device->getUser()->getId()->equals($customer->getId()));
        self::assertSame(self::EXPO_TOKEN, $device->getPushToken());
    }

    public function testRegisterSameTokenTwiceUpsertsSingleRow(): void
    {
        $customer = $this->createUser('device-upsert@example.test', ['ROLE_CUSTOMER']);

        $first = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(), $customer);
        self::assertSame(201, $first->getStatusCode());

        $second = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(['locale' => 'ar']), $customer);
        self::assertSame(200, $second->getStatusCode());

        $devices = $this->deviceRepository()->findAll();
        self::assertCount(1, $devices);
        self::assertSame('ar', $devices[0]->getLocale());
    }

    public function testRegisterReassignsKnownTokenToNewUser(): void
    {
        $customerA = $this->createUser('device-user-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('device-user-b@example.test', ['ROLE_CUSTOMER']);

        $first = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(), $customerA);
        self::assertSame(201, $first->getStatusCode());
        $deviceId = $this->decodeJson($first)['id'];

        // Logout of A revokes the device, then B logs in on the same physical
        // device (same Expo token): the row is reassigned and un-revoked.
        $delete = $this->requestJson('DELETE', '/api/mobile/devices/'.$deviceId, user: $customerA);
        self::assertSame(204, $delete->getStatusCode());

        $second = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(), $customerB);
        self::assertSame(200, $second->getStatusCode());

        $devices = $this->deviceRepository()->findAll();
        self::assertCount(1, $devices);
        self::assertTrue($devices[0]->getUser()->getId()->equals($customerB->getId()));
        self::assertNull($devices[0]->getRevokedAt());
        self::assertNull($devices[0]->getRevocationReason());
        self::assertTrue($devices[0]->isActive());
    }

    public function testRegisterMerchantApplicationWithCustomerRoleReturns403(): void
    {
        $customer = $this->createUser('device-cross-customer@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(['application' => 'merchant']), $customer);

        self::assertSame(403, $response->getStatusCode());
        self::assertCount(0, $this->deviceRepository()->findAll());
    }

    public function testRegisterClientApplicationWithMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('device-cross-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(), $merchant);

        self::assertSame(403, $response->getStatusCode());
        self::assertCount(0, $this->deviceRepository()->findAll());
    }

    public function testRegisterMerchantDeviceForMerchant(): void
    {
        $merchant = $this->createUser('device-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload([
            'application' => 'merchant',
            'platform' => 'ios',
        ]), $merchant);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('merchant', $this->decodeJson($response)['application']);
    }

    public function testRegisterWithoutAuthenticationReturns401(): void
    {
        $response = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testRegisterRejectsInvalidApplication(): void
    {
        $customer = $this->createUser('device-invalid@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/mobile/devices', $this->clientPayload(['application' => 'backoffice']), $customer);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRegisterRejectsMissingToken(): void
    {
        $customer = $this->createUser('device-missing-token@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/mobile/devices', [
            'application' => 'client',
            'platform' => 'android',
        ], $customer);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchUpdatesMetadataAndRefreshesLastSeen(): void
    {
        $customer = $this->createUser('device-patch@example.test', ['ROLE_CUSTOMER']);
        $device = $this->createDevice($customer);
        $previousLastSeen = $device->getLastSeenAt();

        $response = $this->requestJson('PATCH', '/api/mobile/devices/'.$device->getId()->toRfc4122(), [
            'locale' => 'ar',
            'app_version' => '1.3.0',
            'enabled' => false,
        ], $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('ar', $payload['locale']);
        self::assertSame('1.3.0', $payload['app_version']);
        self::assertFalse($payload['enabled']);

        $this->entityManager->refresh($device);
        self::assertSame('ar', $device->getLocale());
        self::assertFalse($device->isEnabled());
        // DB timestamps drop the microseconds — compare at second precision.
        self::assertGreaterThanOrEqual($previousLastSeen->getTimestamp(), $device->getLastSeenAt()->getTimestamp());
    }

    public function testPatchSomeoneElsesDeviceReturns404(): void
    {
        $owner = $this->createUser('device-owner@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('device-other@example.test', ['ROLE_CUSTOMER']);
        $device = $this->createDevice($owner);

        $response = $this->requestJson('PATCH', '/api/mobile/devices/'.$device->getId()->toRfc4122(), [
            'locale' => 'ar',
        ], $other);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchUnknownDeviceReturns404(): void
    {
        $customer = $this->createUser('device-patch-404@example.test', ['ROLE_CUSTOMER']);

        // Pattern #27: real non-existing UUID v4, never the nil UUID.
        $response = $this->requestJson('PATCH', '/api/mobile/devices/550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'ar',
        ], $customer);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteRevokesDeviceAndIsIdempotent(): void
    {
        $customer = $this->createUser('device-delete@example.test', ['ROLE_CUSTOMER']);
        $device = $this->createDevice($customer);
        $deviceId = $device->getId()->toRfc4122();

        $first = $this->requestJson('DELETE', '/api/mobile/devices/'.$deviceId, user: $customer);
        self::assertSame(204, $first->getStatusCode());

        $this->entityManager->refresh($device);
        self::assertNotNull($device->getRevokedAt());
        self::assertSame(MobileDeviceRevocationReason::Logout, $device->getRevocationReason());
        self::assertFalse($device->isActive());

        $second = $this->requestJson('DELETE', '/api/mobile/devices/'.$deviceId, user: $customer);
        self::assertSame(204, $second->getStatusCode());
    }

    public function testDeleteSomeoneElsesDeviceReturns404(): void
    {
        $owner = $this->createUser('device-del-owner@example.test', ['ROLE_CUSTOMER']);
        $other = $this->createUser('device-del-other@example.test', ['ROLE_CUSTOMER']);
        $device = $this->createDevice($owner);

        $response = $this->requestJson('DELETE', '/api/mobile/devices/'.$device->getId()->toRfc4122(), user: $other);

        self::assertSame(404, $response->getStatusCode());
        $this->entityManager->refresh($device);
        self::assertNull($device->getRevokedAt());
    }

    public function testCustomerAccountDeletionPurgesDevices(): void
    {
        $customer = $this->createUser('device-account-delete@example.test', ['ROLE_CUSTOMER']);
        $this->createDevice($customer);

        $response = $this->requestJson('DELETE', '/api/me/account', user: $customer);

        self::assertSame(204, $response->getStatusCode());
        self::assertCount(0, $this->deviceRepository()->findAll());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function clientPayload(array $overrides = []): array
    {
        return array_replace([
            'push_token' => self::EXPO_TOKEN,
            'application' => 'client',
            'platform' => 'android',
        ], $overrides);
    }

    private function createDevice(\App\Entity\User $user, MobileApplication $application = MobileApplication::Client): MobileDevice
    {
        $device = new MobileDevice(
            user: $user,
            application: $application,
            platform: MobilePlatform::Android,
            provider: MobilePushProvider::Expo,
            pushToken: 'ExponentPushToken['.$user->getId()->toRfc4122().']',
        );
        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $device;
    }

    private function deviceRepository(): MobileDeviceRepository
    {
        $repository = $this->entityManager->getRepository(MobileDevice::class);
        self::assertInstanceOf(MobileDeviceRepository::class, $repository);

        return $repository;
    }
}
