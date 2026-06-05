<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Enum\SubscriptionPricingPhase;

final class MerchantBillingDocumentApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanListOnlyTheirMonthlyBillingDocuments(): void
    {
        $merchant = $this->createUser('merchant-billing-own@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-billing-other@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $otherSubscription = Subscription::startTrial($otherMerchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));

        $ownDocument = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000005',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );
        $otherDocument = BillingDocument::issueMonthlyStatement(
            subscription: $otherSubscription,
            documentNumber: 'MS-2026-000006',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($otherSubscription);
        $this->entityManager->persist($ownDocument);
        $this->entityManager->persist($otherDocument);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/billing-documents', user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame($ownDocument->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('MS-2026-000005', $payload['items'][0]['document_number']);
        self::assertSame('Document mensuel interne non fiscal', $payload['items'][0]['document_nature_label']);
    }

    public function testMerchantCanReadOwnBillingDocumentDetail(): void
    {
        $merchant = $this->createUser('merchant-billing-detail@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-08-01T00:00:00+01:00'));
        $document = BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000007',
            billingPeriodStart: new \DateTimeImmutable('2026-08-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-09-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-08-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-08-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Standard,
            amountTnd: '50.000',
        );

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/merchant/billing-documents/%s', $document->getId()), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('MS-2026-000007', $payload['document_number']);
        self::assertSame('monthly_statement', $payload['document_type']);
        self::assertSame('standard', $payload['pricing_phase']);
        self::assertSame('50.000', $payload['amount_due_tnd']);
    }

    public function testMerchantCannotReadAnotherMerchantBillingDocument(): void
    {
        $merchant = $this->createUser('merchant-billing-isolation@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-billing-isolation-other@example.test', ['ROLE_MERCHANT']);
        $otherSubscription = Subscription::startTrial($otherMerchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $otherDocument = BillingDocument::issueMonthlyStatement(
            subscription: $otherSubscription,
            documentNumber: 'MS-2026-000008',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-08T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '10.000',
        );

        $this->entityManager->persist($otherSubscription);
        $this->entityManager->persist($otherDocument);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/merchant/billing-documents/%s', $otherDocument->getId()), user: $merchant);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMerchantBillingDocumentsRequireMerchantRole(): void
    {
        $customer = $this->createUser('customer-billing-forbidden@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/merchant/billing-documents', user: $customer);

        self::assertSame(403, $response->getStatusCode());
    }
}
