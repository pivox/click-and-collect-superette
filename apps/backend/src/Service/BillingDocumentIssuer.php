<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Enum\BillingDocumentType;
use App\Enum\SubscriptionPricingPhase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final readonly class BillingDocumentIssuer
{
    public function __construct(
        private Connection $connection,
        private SubscriptionPricingSchedule $schedule,
    ) {
    }

    public function issueMonthlyStatement(
        Subscription $subscription,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $billingPeriodStart,
        \DateTimeImmutable $billingPeriodEnd,
        SubscriptionPricingPhase $pricingPhase,
        string $amountTnd,
    ): BillingDocument {
        $documentNumber = $this->nextDocumentNumber((int) $issuedAt->format('Y'));

        return BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: $documentNumber,
            billingPeriodStart: $billingPeriodStart,
            billingPeriodEnd: $billingPeriodEnd,
            issuedAt: $issuedAt,
            dueAt: $issuedAt->modify('+7 days'),
            pricingPhase: $pricingPhase,
            amountTnd: $amountTnd,
        );
    }

    public function issueCurrentPeriod(Subscription $subscription, \DateTimeImmutable $issuedAt): ?BillingDocument
    {
        $pricing = $this->schedule->forSubscription($subscription, $issuedAt);
        $amount = $pricing->periodPriceTnd ?? $pricing->monthlyPriceTnd;
        if (bccomp($amount, '0', 3) <= 0) {
            return null;
        }

        return BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: $this->nextDocumentNumber((int) $issuedAt->format('Y')),
            billingPeriodStart: $pricing->currentPeriodStartedAt,
            billingPeriodEnd: $pricing->currentPeriodEndsAt,
            issuedAt: $issuedAt,
            dueAt: $issuedAt->modify('+7 days'),
            pricingPhase: $pricing->pricingPhase,
            amountTnd: $amount,
            pricingSnapshot: ['pricing_terms' => $pricing->pricingTerms, 'period_breakdown' => $pricing->periodBreakdown, 'period_price_tnd' => $amount, 'billing_interval' => $pricing->billingInterval],
            documentType: 'annual' === $pricing->billingInterval ? BillingDocumentType::AnnualStatement : BillingDocumentType::MonthlyStatement,
        );
    }

    private function nextDocumentNumber(int $year): string
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $sequence = (int) $this->connection
                ->executeQuery("SELECT nextval('billing_document_number_seq')")
                ->fetchOne();

            return \sprintf('MS-%d-%06d', $year, $sequence);
        }

        $existing = (int) $this->connection
            ->executeQuery('SELECT COUNT(*) FROM billing_documents')
            ->fetchOne();

        return \sprintf('MS-%d-%06d', $year, $existing + 1);
    }
}
