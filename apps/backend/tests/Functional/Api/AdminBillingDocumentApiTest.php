<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionPricingPhase;
use Symfony\Component\Uid\Uuid;

final class AdminBillingDocumentApiTest extends FunctionalApiTestCase
{
    public function testAdminCanListMonthlyBillingDocuments(): void
    {
        $admin = $this->createUser('admin-billing-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-billing-list@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000001',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/billing-documents', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(1, $payload['items']);
        self::assertSame($document->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('MS-2026-000001', $payload['items'][0]['document_number']);
        self::assertSame('monthly_statement', $payload['items'][0]['document_type']);
        self::assertSame('Document mensuel interne non fiscal', $payload['items'][0]['document_nature_label']);
        self::assertSame('issued', $payload['items'][0]['status']);
        self::assertSame('promo', $payload['items'][0]['pricing_phase']);
        self::assertSame('10.000', $payload['items'][0]['amount_tnd']);
        self::assertSame('TND', $payload['items'][0]['currency']);
        self::assertSame('merchant-billing-list@example.test', $payload['items'][0]['merchant_email']);
    }

    public function testAdminCanReadBillingDocumentDetail(): void
    {
        $admin = $this->createUser('admin-billing-detail@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-billing-detail@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-07-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000002',
            billingPeriodStart: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-08-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-07-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-07-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Standard,
            amountTnd: '50.000',
        );
        $document->markPaid(new \DateTimeImmutable('2026-07-05T10:00:00+01:00'));

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/admin/billing-documents/%s', $document->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($document->getId()->toRfc4122(), $payload['id']);
        self::assertSame($subscription->getId()->toRfc4122(), $payload['subscription_id']);
        self::assertSame('MS-2026-000002', $payload['document_number']);
        self::assertSame('paid', $payload['status']);
        self::assertSame('50.000', $payload['amount_paid_tnd']);
        self::assertSame('0.000', $payload['amount_due_tnd']);
        self::assertCount(7, $payload['reminder_schedule']);
        self::assertSame('j_minus_7', $payload['reminder_schedule'][0]['stage']);
        self::assertSame('not_applicable', $payload['reminder_schedule'][0]['email_status']);
        self::assertSame('2026-07-01T23:59:59+01:00', $payload['reminder_schedule'][0]['scheduled_at']);
        self::assertNull($payload['whatsapp_manual_contacted_at']);
    }

    public function testMerchantIsForbiddenFromAdminBillingDocumentEndpoints(): void
    {
        $merchant = $this->createUser('merchant-billing-admin-forbidden@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000003',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $listResponse = $this->requestJson('GET', '/api/admin/billing-documents', user: $merchant);
        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/billing-documents/%s', $document->getId()), user: $merchant);

        self::assertSame(403, $listResponse->getStatusCode());
        self::assertSame(403, $detailResponse->getStatusCode());
    }

    public function testAdminBillingDocumentDetailReturns404WhenMissing(): void
    {
        $admin = $this->createUser('admin-billing-missing@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', \sprintf('/api/admin/billing-documents/%s', Uuid::v4()), user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAdminCanOpenWhatsappContactForBillingDocumentAndTraceAction(): void
    {
        $admin = $this->createUser('admin-billing-whatsapp@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-billing-whatsapp@example.test', ['ROLE_MERCHANT']);
        $merchant->setPhone('+216 20 123 456');
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000005',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', \sprintf('/api/admin/billing-documents/%s/whatsapp-contact', $document->getId()), user: $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($document->getId()->toRfc4122(), $payload['billing_document_id']);
        self::assertSame('21620123456', $payload['phone']);
        self::assertStringStartsWith('https://wa.me/21620123456?text=', $payload['whatsapp_url']);
        self::assertStringContainsString('MS-2026-000005', $payload['message']);
        self::assertStringContainsString('10.000 TND', $payload['message']);
        self::assertStringNotContainsString('paiement en ligne', $payload['message']);

        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy([
            'action' => 'subscription_payment_reminder.whatsapp_contacted',
            'resourceType' => 'billing_document',
            'resourceId' => $document->getId()->toRfc4122(),
        ]);
        self::assertNotNull($auditLog);
    }

    public function testAdminCannotOpenWhatsappContactForPaidBillingDocument(): void
    {
        $admin = $this->createUser('admin-billing-whatsapp-paid@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-billing-whatsapp-paid@example.test', ['ROLE_MERCHANT']);
        $merchant->setPhone('+216 20 123 456');
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000006',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );
        $document->markPaid(new \DateTimeImmutable('2026-06-04T10:00:00+01:00'));

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', \sprintf('/api/admin/billing-documents/%s/whatsapp-contact', $document->getId()), user: $admin);

        self::assertSame(409, $response->getStatusCode());

        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy([
            'action' => 'subscription_payment_reminder.whatsapp_contacted',
            'resourceType' => 'billing_document',
            'resourceId' => $document->getId()->toRfc4122(),
        ]);
        self::assertNull($auditLog);
    }

    public function testBillingDocumentCanMoveToOverdue(): void
    {
        $merchant = $this->createUser('merchant-billing-overdue@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000004',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );

        $document->markOverdue(new \DateTimeImmutable('2026-06-09T00:00:00+01:00'));

        self::assertSame(BillingDocumentStatus::Overdue, $document->getStatus());
    }
}
