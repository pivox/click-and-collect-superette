<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Shop;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

final class MerchantStoreQrApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerReadsQrCode(): void
    {
        $merchant = $this->createMerchant('merchant-qr-owner@example.test');
        $shop = $this->createStore($merchant, 'Supérette El Amal', 'superette-el-amal', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame('Supérette El Amal', $payload['store_name']);
        self::assertSame('superette-el-amal', $payload['slug']);
        self::assertSame($shop->getQrCodeToken(), $payload['qr_code_token']);
        self::assertSame(\sprintf('http://localhost:3000/stores/by-qr/%s', $shop->getQrCodeToken()), $payload['target_url']);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('owner', $payload);
    }

    public function testTargetUrlIsAbsoluteFrontendStoreUrl(): void
    {
        $merchant = $this->createMerchant('merchant-qr-target@example.test');
        $shop = $this->createStore($merchant, 'Supérette Cible', 'superette-cible', 'Sfax');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $expectedTargetUrl = \sprintf('http://localhost:3000/stores/by-qr/%s', $shop->getQrCodeToken());
        self::assertSame($expectedTargetUrl, $payload['target_url']);
    }

    public function testMerchantOwnerDownloadsQrCodePng(): void
    {
        $merchant = $this->createMerchant('merchant-qr-png@example.test');
        $shop = $this->createStore($merchant, 'Supérette PNG', 'superette-png', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code.png', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="qr-superette-png.png"', (string) $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith("\x89PNG\r\n\x1A\n", $this->captureResponseContent($response));
    }

    public function testMerchantOwnerDownloadsQrCodePdf(): void
    {
        $merchant = $this->createMerchant('merchant-qr-pdf@example.test');
        $shop = $this->createStore($merchant, 'Supérette PDF', 'superette-pdf', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code.pdf', $shop->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="qr-superette-pdf.pdf"', (string) $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', $this->captureResponseContent($response));
    }

    public function testOtherMerchantCannotDownloadQrCodePng(): void
    {
        $owner = $this->createMerchant('merchant-qr-png-owner@example.test');
        $otherMerchant = $this->createMerchant('merchant-qr-png-intruder@example.test');
        $shop = $this->createStore($owner, 'Supérette PNG Privée', 'superette-png-privee', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code.png', $shop->getId()), user: $otherMerchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testOtherMerchantIsDenied(): void
    {
        $owner = $this->createMerchant('merchant-qr-real-owner@example.test');
        $otherMerchant = $this->createMerchant('merchant-qr-intruder@example.test');
        $shop = $this->createStore($owner, 'Supérette Privée', 'superette-privee', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', $shop->getId()), user: $otherMerchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerIsDenied(): void
    {
        $merchant = $this->createMerchant('merchant-qr-customer-test@example.test');
        $customer = $this->createUser('customer-qr-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createStore($merchant, 'Supérette Customer Test', 'superette-customer-test', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', $shop->getId()), user: $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousIsUnauthorized(): void
    {
        $merchant = $this->createMerchant('merchant-qr-anon-test@example.test');
        $shop = $this->createStore($merchant, 'Supérette Anon Test', 'superette-anon-test', 'Tunis');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', $shop->getId()));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMissingStoreReturnsNotFound(): void
    {
        $merchant = $this->createMerchant('merchant-qr-missing@example.test');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/qr-code', Uuid::v4()), user: $merchant);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidUuidReturnsNotFound(): void
    {
        $merchant = $this->createMerchant('merchant-qr-invalid-uuid@example.test');

        $response = $this->requestJson('GET', '/api/merchant/stores/not-a-uuid/qr-code', user: $merchant);

        self::assertSame(404, $response->getStatusCode());
    }

    private function createMerchant(string $email): User
    {
        return $this->createUser($email, ['ROLE_MERCHANT']);
    }

    private function createStore(User $owner, string $name, string $slug, string $city): Shop
    {
        $shop = $this->createShop($owner);
        $shop
            ->setName($name)
            ->setSlug($slug)
            ->setCity($city);
        $this->entityManager->flush();

        return $shop;
    }

    private function captureResponseContent(\Symfony\Component\HttpFoundation\Response $response): string
    {
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        self::assertIsString($content);

        return $content;
    }
}
