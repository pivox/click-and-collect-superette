<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Subscription;
use App\Enum\BillingDocumentStatus;
use App\Service\BillingDocumentIssuer;
use App\Service\SubscriptionPricingPhaseRefresher;

final class SubscriptionBillingPricingTest extends FunctionalApiTestCase
{
    public function testAnnualDocumentFreePeriodAndRefreshUseCustomPricing(): void
    {
        $merchant = $this->createUser('billing-custom@test.com', ['ROLE_MERCHANT']);
        $start = new \DateTimeImmutable('2026-01-31T10:00:00+01:00');
        $subscription = Subscription::startTrial($merchant, $start);
        $subscription->schedulePricing(['billing_interval' => 'annual', 'pricing_mode' => 'paid', 'base_price_tnd' => '600.000', 'free_months' => null, 'discounts' => [], 'effective_at' => $start->format(\DATE_ATOM)], $start);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
        $issuer = self::getContainer()->get(BillingDocumentIssuer::class);
        $document = $issuer->issueCurrentPeriod($subscription, new \DateTimeImmutable('2026-02-15'));
        self::assertNotNull($document);
        self::assertSame('600.000', $document->getAmountDueTnd());
        self::assertSame('annual_statement', $document->getDocumentType()->value);
        self::assertSame('2027-01-31T10:00:00+01:00', $document->getBillingPeriodEnd()->format(\DATE_ATOM));
        self::assertCount(12, $document->getPricingSnapshot()['period_breakdown']);
        $this->entityManager->persist($document);
        $this->entityManager->flush();
        $subscription->schedulePricing(['billing_interval' => 'monthly', 'pricing_mode' => 'free', 'base_price_tnd' => '50.000', 'free_months' => null, 'discounts' => [], 'effective_at' => '2027-01-31T10:00:00+01:00'], new \DateTimeImmutable('2026-03-01'));
        $this->entityManager->flush();
        self::assertNull($issuer->issueCurrentPeriod($subscription, new \DateTimeImmutable('2027-02-15')));
        self::assertSame(BillingDocumentStatus::Issued, $document->getStatus());
        self::assertSame('600.000', $document->getAmountDueTnd());
        $refresher = self::getContainer()->get(SubscriptionPricingPhaseRefresher::class);
        self::assertSame(1, $refresher->refreshDuePricingPhases(new \DateTimeImmutable('2027-02-15')));
        self::assertSame('0.000', $subscription->getMonthlyPriceTnd());
        self::assertSame('2027-02-28T10:00:00+01:00', $subscription->getCurrentPeriodEndsAt()->format(\DATE_ATOM));
        self::assertCount(1, self::getContainer()->get(\App\Repository\BillingDocumentRepository::class)->findPaymentReminderCandidates());
        self::assertSame('600.000', $document->getPricingSnapshot()['period_price_tnd']);
    }

    public function testTinyAnnualAmountStillCreatesCollectibleDocument(): void
    {
        $merchant = $this->createUser('tiny-annual@test.com', ['ROLE_MERCHANT']);
        $now = new \DateTimeImmutable();
        $subscription = Subscription::startTrial($merchant, $now);
        $subscription->schedulePricing(['billing_interval' => 'annual', 'pricing_mode' => 'paid', 'base_price_tnd' => '0.001', 'free_months' => null, 'discounts' => [], 'effective_at' => $now->format(\DATE_ATOM)], $now);
        self::getContainer()->get(\App\Service\SubscriptionPricingSchedule::class)->refresh($subscription, $now);
        self::assertSame('0.000', $subscription->getMonthlyPriceTnd());
        $this->entityManager->persist($subscription);
        $document = self::getContainer()->get(BillingDocumentIssuer::class)->issueCurrentPeriod($subscription, $now);
        self::assertNotNull($document);
        self::assertSame('0.001', $document->getAmountDueTnd());
        $this->entityManager->persist($document);
        $this->entityManager->flush();
        self::assertCount(1, self::getContainer()->get(\App\Repository\BillingDocumentRepository::class)->findPaymentReminderCandidates());
    }
}
