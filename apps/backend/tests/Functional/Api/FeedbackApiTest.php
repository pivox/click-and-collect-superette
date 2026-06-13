<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\FeedbackEntry;

final class FeedbackApiTest extends FunctionalApiTestCase
{
    public function testConnectedUserCanReadCurrentSettingsAndCreateFeedbackWhenEnabled(): void
    {
        $admin = $this->createUser('admin-feedback-settings-enable@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-feedback-create@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $settingsResponse = $this->requestJson('PUT', '/api/admin/feedback/settings', [
            'globalEnabled' => true,
            'clientEnabled' => true,
            'merchantEnabled' => true,
            'adminEnabled' => false,
            'clientAreaEnabled' => true,
            'merchantAreaEnabled' => true,
            'adminAreaEnabled' => false,
        ], $admin);
        self::assertSame(200, $settingsResponse->getStatusCode(), (string) $settingsResponse->getContent());

        $currentResponse = $this->requestJson('GET', '/api/feedback/settings/current?appArea=merchant&appSubArea=merchant_orders', user: $merchant);
        self::assertSame(200, $currentResponse->getStatusCode(), (string) $currentResponse->getContent());
        $currentPayload = $this->decodeJson($currentResponse);
        self::assertTrue($currentPayload['enabled']);
        self::assertSame('merchant', $currentPayload['appArea']);
        self::assertSame('merchant_orders', $currentPayload['appSubArea']);
        self::assertTrue($currentPayload['requireAuthenticatedUser']);

        $response = $this->requestJson('POST', '/api/feedback', [
            'type' => 'confusing',
            'message' => 'Le filtre des commandes marchand manque de clarté.',
            'appArea' => 'merchant',
            'appSubArea' => 'merchant_orders',
            'pageUrl' => '/merchant/commandes',
            'routeName' => 'merchant_orders',
            'pageTitle' => 'Commandes marchand',
            'locale' => 'fr',
            'viewportWidth' => 390,
            'viewportHeight' => 844,
            'shopId' => $shop->getId()->toRfc4122(),
            'contactConsent' => true,
        ], $merchant);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('unread', $payload['status']);
        self::assertSame('confusing', $payload['type']);
        self::assertSame('merchant', $payload['appArea']);
        self::assertSame('merchant_orders', $payload['appSubArea']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['user']['id']);
        self::assertSame($shop->getId()->toRfc4122(), $payload['shop']['id']);
        self::assertTrue($payload['contactConsent']);

        $stored = $this->entityManager->getRepository(FeedbackEntry::class)->find($payload['id']);
        self::assertInstanceOf(FeedbackEntry::class, $stored);
        self::assertSame('Le filtre des commandes marchand manque de clarté.', $stored->getMessage());
        self::assertSame('merchant_orders', $stored->getAppSubArea());
        self::assertSame('/merchant/commandes', $stored->getPageUrl());
        self::assertTrue($stored->hasContactConsent());
    }

    public function testFeedbackCreationRespectsGlobalRoleAreaAndMessageValidation(): void
    {
        $admin = $this->createUser('admin-feedback-disabled@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-feedback-disabled@example.test', ['ROLE_CUSTOMER']);

        self::assertSame(401, $this->requestJson('GET', '/api/feedback/settings/current?appArea=client')->getStatusCode());

        $disabledResponse = $this->requestJson('POST', '/api/feedback', [
            'type' => 'bug',
            'message' => 'Le bouton de validation ne répond pas.',
            'appArea' => 'client',
        ], $customer);
        self::assertSame(403, $disabledResponse->getStatusCode(), (string) $disabledResponse->getContent());

        $this->requestJson('PUT', '/api/admin/feedback/settings', [
            'globalEnabled' => true,
            'clientEnabled' => false,
            'merchantEnabled' => true,
            'adminEnabled' => true,
            'clientAreaEnabled' => true,
            'merchantAreaEnabled' => true,
            'adminAreaEnabled' => true,
        ], $admin);

        $roleDisabledResponse = $this->requestJson('POST', '/api/feedback', [
            'type' => 'bug',
            'message' => 'Le bouton de validation ne répond pas.',
            'appArea' => 'client',
        ], $customer);
        self::assertSame(403, $roleDisabledResponse->getStatusCode(), (string) $roleDisabledResponse->getContent());

        $this->requestJson('PUT', '/api/admin/feedback/settings', [
            'globalEnabled' => true,
            'clientEnabled' => true,
            'merchantEnabled' => true,
            'adminEnabled' => true,
            'clientAreaEnabled' => false,
            'merchantAreaEnabled' => true,
            'adminAreaEnabled' => true,
        ], $admin);

        $areaDisabledResponse = $this->requestJson('POST', '/api/feedback', [
            'type' => 'bug',
            'message' => 'Le bouton de validation ne répond pas.',
            'appArea' => 'client',
        ], $customer);
        self::assertSame(403, $areaDisabledResponse->getStatusCode(), (string) $areaDisabledResponse->getContent());

        $this->requestJson('PUT', '/api/admin/feedback/settings', [
            'globalEnabled' => true,
            'clientEnabled' => true,
            'merchantEnabled' => true,
            'adminEnabled' => true,
            'clientAreaEnabled' => true,
            'merchantAreaEnabled' => true,
            'adminAreaEnabled' => true,
        ], $admin);

        $tooShortResponse = $this->requestJson('POST', '/api/feedback', [
            'type' => 'idea',
            'message' => 'abcd',
            'appArea' => 'client',
        ], $customer);
        self::assertSame(422, $tooShortResponse->getStatusCode(), (string) $tooShortResponse->getContent());

        $tooLongResponse = $this->requestJson('POST', '/api/feedback', [
            'type' => 'idea',
            'message' => str_repeat('a', 2001),
            'appArea' => 'client',
        ], $customer);
        self::assertSame(422, $tooLongResponse->getStatusCode(), (string) $tooLongResponse->getContent());
    }
}
