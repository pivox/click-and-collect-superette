<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\SubscriptionOutput;
use App\Entity\Subscription;
use App\Service\SubscriptionPricingSchedule;

final readonly class SubscriptionOutputFactory
{
    public function __construct(
        private SubscriptionPricingSchedule $pricingSchedule,
    ) {
    }

    public function fromSubscription(Subscription $subscription, ?\DateTimeImmutable $now = null): SubscriptionOutput
    {
        $pricing = $this->pricingSchedule->calculate(
            $subscription->getStartedAt(),
            $now ?? new \DateTimeImmutable(),
        );
        $merchant = $subscription->getMerchant();

        return new SubscriptionOutput(
            id: $subscription->getId()->toRfc4122(),
            merchantId: $merchant->getId()->toRfc4122(),
            merchantEmail: $merchant->getEmail(),
            lifecycle: $subscription->getLifecycle()->value,
            pricingPhase: $pricing->pricingPhase->value,
            monthlyPriceTnd: $pricing->monthlyPriceTnd,
            currency: $subscription->getCurrency(),
            startedAt: $subscription->getStartedAt()->format(\DateTimeInterface::ATOM),
            currentPeriodStartedAt: $pricing->currentPeriodStartedAt->format(\DateTimeInterface::ATOM),
            currentPeriodEndsAt: $pricing->currentPeriodEndsAt->format(\DateTimeInterface::ATOM),
            nextPhaseChangeAt: $pricing->nextPhaseChangeAt?->format(\DateTimeInterface::ATOM),
            createdAt: $subscription->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
