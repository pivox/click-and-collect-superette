<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;

final class AdminFeedbackApiTest extends FunctionalApiTestCase
{
    public function testAdminCanManageSettingsListDetailAndStatuses(): void
    {
        $admin = $this->createUser('admin-feedback-manage@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-feedback-manage@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-feedback-manage@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);

        self::assertSame(403, $this->requestJson('GET', '/api/admin/feedback/settings', user: $customer)->getStatusCode());

        $settingsResponse = $this->requestJson('PUT', '/api/admin/feedback/settings', [
            'globalEnabled' => true,
            'clientEnabled' => true,
            'merchantEnabled' => true,
            'adminEnabled' => true,
            'clientAreaEnabled' => true,
            'merchantAreaEnabled' => true,
            'adminAreaEnabled' => true,
        ], $admin);
        self::assertSame(200, $settingsResponse->getStatusCode(), (string) $settingsResponse->getContent());
        $settingsPayload = $this->decodeJson($settingsResponse);
        self::assertTrue($settingsPayload['globalEnabled']);
        self::assertTrue($settingsPayload['merchantEnabled']);
        self::assertTrue($settingsPayload['requireAuthenticatedUser']);

        $merchantFeedback = $this->decodeJson($this->requestJson('POST', '/api/feedback', [
            'type' => 'bug',
            'message' => 'Le scan de retrait marchand échoue sur mobile.',
            'appArea' => 'merchant',
            'appSubArea' => 'merchant_pickup',
            'pageUrl' => '/merchant/retrait',
            'routeName' => 'merchant_pickup',
            'pageTitle' => 'Retrait',
            'locale' => 'fr',
            'viewportWidth' => 430,
            'viewportHeight' => 932,
            'shopId' => $shop->getId()->toRfc4122(),
            'contactConsent' => true,
        ], $merchant));

        $this->requestJson('POST', '/api/feedback', [
            'type' => 'idea',
            'message' => 'Ajouter un raccourci vers la Kadhia active.',
            'appArea' => 'client',
            'appSubArea' => 'client_kadhia',
            'pageUrl' => '/kadhia',
            'contactConsent' => false,
        ], $customer);

        $listResponse = $this->requestJson('GET', \sprintf(
            '/api/admin/feedbacks?status=unread&userRole=merchant&type=bug&appArea=merchant&shop=%s&contactConsent=true&page=1&limit=10',
            $shop->getId()->toRfc4122(),
        ), user: $admin);
        self::assertSame(200, $listResponse->getStatusCode(), (string) $listResponse->getContent());
        $listPayload = $this->decodeJson($listResponse);
        self::assertSame(1, $listPayload['total']);
        self::assertCount(1, $listPayload['items']);
        self::assertSame($merchantFeedback['id'], $listPayload['items'][0]['id']);
        self::assertSame('Le scan de retrait marchand échoue sur mobile.', $listPayload['items'][0]['messageExcerpt']);

        $detailResponse = $this->requestJson('GET', '/api/admin/feedbacks/'.$merchantFeedback['id'], user: $admin);
        self::assertSame(200, $detailResponse->getStatusCode(), (string) $detailResponse->getContent());
        $detailPayload = $this->decodeJson($detailResponse);
        self::assertSame($merchantFeedback['id'], $detailPayload['id']);
        self::assertSame('Le scan de retrait marchand échoue sur mobile.', $detailPayload['message']);
        self::assertSame('/merchant/retrait', $detailPayload['pageUrl']);
        self::assertSame('merchant_pickup', $detailPayload['routeName']);
        self::assertSame('Retrait', $detailPayload['pageTitle']);
        self::assertSame(430, $detailPayload['viewportWidth']);
        self::assertSame($merchant->getId()->toRfc4122(), $detailPayload['user']['id']);
        self::assertSame($shop->getId()->toRfc4122(), $detailPayload['shop']['id']);
        self::assertTrue($detailPayload['contactConsent']);
        self::assertNull($detailPayload['adminNote']);

        $readPayload = $this->decodeJson($this->requestJson('PATCH', '/api/admin/feedbacks/'.$merchantFeedback['id'].'/read', [], $admin));
        self::assertSame('read', $readPayload['status']);

        $resolvedPayload = $this->decodeJson($this->requestJson('PATCH', '/api/admin/feedbacks/'.$merchantFeedback['id'].'/resolve', [
            'adminNote' => 'Corrigé dans le flux retrait marchand.',
        ], $admin));
        self::assertSame('resolved', $resolvedPayload['status']);
        self::assertSame('Corrigé dans le flux retrait marchand.', $resolvedPayload['adminNote']);
        self::assertNotNull($resolvedPayload['resolvedAt']);

        $readAfterResolvedPayload = $this->decodeJson($this->requestJson('PATCH', '/api/admin/feedbacks/'.$merchantFeedback['id'].'/read', [], $admin));
        self::assertSame('read', $readAfterResolvedPayload['status']);
        self::assertNull($readAfterResolvedPayload['resolvedAt']);

        $reopenedPayload = $this->decodeJson($this->requestJson('PATCH', '/api/admin/feedbacks/'.$merchantFeedback['id'].'/reopen', [], $admin));
        self::assertSame('read', $reopenedPayload['status']);

        $unreadPayload = $this->decodeJson($this->requestJson('PATCH', '/api/admin/feedbacks/'.$merchantFeedback['id'].'/unread', [], $admin));
        self::assertSame('unread', $unreadPayload['status']);

        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'feedback.settings_update']));
        self::assertSame(2, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'feedback.read']));
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'feedback.resolve']));
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'feedback.reopen']));
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'feedback.unread']));
    }

    public function testAdminFeedbackSecurityAndInvalidFilters(): void
    {
        $admin = $this->createUser('admin-feedback-invalid@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-feedback-forbidden@example.test', ['ROLE_MERCHANT']);

        self::assertSame(403, $this->requestJson('GET', '/api/admin/feedbacks', user: $merchant)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/feedbacks?status=bad', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/feedbacks?type=bad', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/feedbacks?appArea=bad', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/feedbacks?userRole=bad', user: $admin)->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/admin/feedbacks?shop=bad-uuid', user: $admin)->getStatusCode());
    }
}
