<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPricingPhase;

final class AdminSubscriptionPaymentApiTest extends FunctionalApiTestCase
{
    public function testAdminCanRecordCashPaymentAndMarkBillingDocumentPaid(): void
    {
        $admin = $this->createUser('admin-payment-cash@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-payment-cash@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $subscription->setLifecycle(SubscriptionLifecycle::PaymentDue);
        $document = $this->createBillingDocument($subscription, 'MS-2026-000101', '10.000');
        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/admin/subscription-payments', [
            'billing_document_id' => $document->getId()->toRfc4122(),
            'amount_tnd' => '10.000',
            'method' => 'cash',
            'paid_at' => '2026-06-04T10:30:00+01:00',
            'reference' => 'Caisse Tunis 42',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($document->getId()->toRfc4122(), $payload['billing_document_id']);
        self::assertSame($subscription->getId()->toRfc4122(), $payload['subscription_id']);
        self::assertSame('cash', $payload['method']);
        self::assertSame('confirmed', $payload['status']);
        self::assertSame('10.000', $payload['amount_tnd']);
        self::assertSame('merchant-payment-cash@example.test', $payload['merchant_email']);
        self::assertSame($admin->getId()->toRfc4122(), $payload['validated_by_admin_id']);

        $this->entityManager->refresh($document);
        $this->entityManager->refresh($subscription);
        self::assertSame(BillingDocumentStatus::Paid, $document->getStatus());
        self::assertSame('0.000', $document->getAmountDueTnd());
        self::assertSame(SubscriptionLifecycle::Active, $subscription->getLifecycle());

        $auditLog = $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy([
            'action' => 'subscription_payment.confirmed',
            'resourceType' => 'subscription_payment',
        ]);
        self::assertNotNull($auditLog);
        self::assertSame($payload['id'], $auditLog->getResourceId());
    }

    public function testAdminCanRecordBankTransferWithReference(): void
    {
        $admin = $this->createUser('admin-payment-transfer@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-payment-transfer@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-07-01T00:00:00+01:00'));
        $document = $this->createBillingDocument($subscription, 'MS-2026-000102', '50.000');
        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/admin/subscription-payments', [
            'billing_document_id' => $document->getId()->toRfc4122(),
            'amount_tnd' => '50.000',
            'method' => 'bank_transfer',
            'paid_at' => '2026-06-04T12:00:00+01:00',
            'reference' => 'VIR-2026-00042',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('bank_transfer', $payload['method']);
        self::assertSame('VIR-2026-00042', $payload['reference']);
    }

    public function testAdminPaymentOnAlreadyPaidDocumentReturns409(): void
    {
        $admin = $this->createUser('admin-payment-duplicate@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-payment-duplicate@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = $this->createBillingDocument($subscription, 'MS-2026-000103', '10.000');
        $document->markPaid(new \DateTimeImmutable('2026-06-02T09:00:00+01:00'));
        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/admin/subscription-payments', [
            'billing_document_id' => $document->getId()->toRfc4122(),
            'amount_tnd' => '10.000',
            'method' => 'cash',
            'paid_at' => '2026-06-04T10:30:00+01:00',
        ], $admin);

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('SUBSCRIPTION_PAYMENT_DOCUMENT_ALREADY_PAID', (string) $response->getContent());
    }

    public function testAdminPaymentRejectsInvalidPayload(): void
    {
        $admin = $this->createUser('admin-payment-invalid@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/subscription-payments', [
            'billing_document_id' => 'not-a-uuid',
            'amount_tnd' => '0.000',
            'method' => 'card',
            'paid_at' => '2099-01-01T00:00:00+01:00',
        ], $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantIsForbiddenFromAdminPaymentEndpoint(): void
    {
        $merchant = $this->createUser('merchant-payment-forbidden@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/subscription-payments', [], $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    private function createBillingDocument(Subscription $subscription, string $number, string $amount): BillingDocument
    {
        return BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: $number,
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: $amount,
        );
    }
}
