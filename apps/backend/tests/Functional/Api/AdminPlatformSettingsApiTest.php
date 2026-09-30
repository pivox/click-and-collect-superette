<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;

final class AdminPlatformSettingsApiTest extends FunctionalApiTestCase
{
    public function testAdminReadsSeededFrontendOrigin(): void
    {
        $admin = $this->createUser('admin-platform-settings-read@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/platform/settings', user: $admin);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('platform-settings', $payload['id']);
        self::assertSame('http://localhost:3000', $payload['frontendOrigin']);
        self::assertNotEmpty($payload['updatedAt']);
    }

    public function testAdminUpdatesFrontendOriginAndItDrivesQrAndKadhiaShareUrls(): void
    {
        $admin = $this->createUser('admin-platform-settings-update@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-platform-settings-url@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-platform-settings-url@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);

        $updateResponse = $this->requestJson('PUT', '/api/admin/platform/settings', [
            'frontendOrigin' => 'https://demo.kadhia.tn/',
        ], $admin);

        self::assertSame(200, $updateResponse->getStatusCode(), (string) $updateResponse->getContent());
        $settingsPayload = $this->decodeJson($updateResponse);
        self::assertSame('https://demo.kadhia.tn', $settingsPayload['frontendOrigin']);

        $merchantQrResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', $shop->getId()), user: $merchant);
        self::assertSame(200, $merchantQrResponse->getStatusCode(), (string) $merchantQrResponse->getContent());
        self::assertSame(
            \sprintf('https://demo.kadhia.tn/stores/by-qr/%s', $shop->getQrCodeToken()),
            $this->decodeJson($merchantQrResponse)['target_url'],
        );

        $adminQrResponse = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/qr-code', $shop->getId()), user: $admin);
        self::assertSame(200, $adminQrResponse->getStatusCode(), (string) $adminQrResponse->getContent());
        self::assertSame(
            \sprintf('https://demo.kadhia.tn/stores/by-qr/%s', $shop->getQrCodeToken()),
            $this->decodeJson($adminQrResponse)['target_url'],
        );

        $createKadhiaResponse = $this->requestJson(
            'POST',
            \sprintf('/api/me/stores/%s/kadhias', $shop->getId()),
            [],
            $customer,
        );
        self::assertSame(201, $createKadhiaResponse->getStatusCode(), (string) $createKadhiaResponse->getContent());
        $kadhiaId = $this->decodeJson($createKadhiaResponse)['id'];

        $shareResponse = $this->requestJson('POST', \sprintf('/api/me/kadhias/%s/share-links', $kadhiaId), user: $customer);
        self::assertSame(200, $shareResponse->getStatusCode(), (string) $shareResponse->getContent());
        self::assertStringStartsWith('https://demo.kadhia.tn/kadhia/share/', $this->decodeJson($shareResponse)['share_url']);

        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'platform.settings_update']));
    }

    public function testAdminCannotSetFrontendOriginWithPathQueryOrUnsupportedScheme(): void
    {
        $admin = $this->createUser('admin-platform-settings-invalid@example.test', ['ROLE_ADMIN']);

        foreach ([
            'https://demo.kadhia.tn/app',
            'https://demo.kadhia.tn?debug=1',
            'ftp://demo.kadhia.tn',
            'demo.kadhia.tn',
            'https://user:pass@demo.kadhia.tn',
        ] as $frontendOrigin) {
            $response = $this->requestJson('PUT', '/api/admin/platform/settings', [
                'frontendOrigin' => $frontendOrigin,
            ], $admin);

            self::assertSame(400, $response->getStatusCode(), $frontendOrigin.' should be rejected.');
        }
    }

    public function testOnlyAdminCanManagePlatformSettings(): void
    {
        $merchant = $this->createUser('merchant-platform-settings-forbidden@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-platform-settings-forbidden@example.test', ['ROLE_CUSTOMER']);

        self::assertSame(403, $this->requestJson('GET', '/api/admin/platform/settings', user: $merchant)->getStatusCode());
        self::assertSame(403, $this->requestJson('PUT', '/api/admin/platform/settings', ['frontendOrigin' => 'https://demo.kadhia.tn'], $customer)->getStatusCode());
        self::assertSame(401, $this->requestJson('GET', '/api/admin/platform/settings')->getStatusCode());
    }
}
