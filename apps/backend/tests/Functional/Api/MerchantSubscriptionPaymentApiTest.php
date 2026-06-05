<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Entity\SubscriptionPayment;
use App\Enum\SubscriptionPaymentMethod;
use App\Enum\SubscriptionPricingPhase;

final class MerchantSubscriptionPaymentApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanListOnlyTheirSubscriptionPayments(): void
    {
        $merchant = $this->createUser('merchant-payment-list@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('other-merchant-payment-list@example.test', ['ROLE_MERCHANT']);
        $admin = $this->createUser('admin-payment-list@example.test', ['ROLE_ADMIN']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $otherSubscription = Subscription::startTrial($otherMerchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = $this->createBillingDocument($subscription, 'MS-2026-000201', '10.000');
        $otherDocument = $this->createBillingDocument($otherSubscription, 'MS-2026-000202', '10.000');
        $payment = SubscriptionPayment::confirm(
            billingDocument: $document,
            amountTnd: '10.000',
            method: SubscriptionPaymentMethod::Cash,
            paidAt: new \DateTimeImmutable('2026-06-04T10:30:00+01:00'),
            createdByAdmin: $admin,
            reference: null,
        );
        $otherPayment = SubscriptionPayment::confirm(
            billingDocument: $otherDocument,
            amountTnd: '10.000',
            method: SubscriptionPaymentMethod::Cash,
            paidAt: new \DateTimeImmutable('2026-06-04T10:30:00+01:00'),
            createdByAdmin: $admin,
            reference: null,
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($otherSubscription);
        $this->entityManager->persist($document);
        $this->entityManager->persist($otherDocument);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($otherPayment);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/subscription-payments', user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($payment->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('cash', $payload['items'][0]['method']);
        self::assertSame('confirmed', $payload['items'][0]['status']);
    }

    public function testAdminCanListSubscriptionPayments(): void
    {
        $admin = $this->createUser('admin-payment-admin-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-payment-admin-list@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $document = $this->createBillingDocument($subscription, 'MS-2026-000203', '10.000');
        $payment = SubscriptionPayment::confirm(
            billingDocument: $document,
            amountTnd: '10.000',
            method: SubscriptionPaymentMethod::Cash,
            paidAt: new \DateTimeImmutable('2026-06-04T10:30:00+01:00'),
            createdByAdmin: $admin,
            reference: null,
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/subscription-payments', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($payment->getId()->toRfc4122(), $payload['items'][0]['id']);
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
