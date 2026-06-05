<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SubscriptionPricingPhaseRefresher
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private SubscriptionPricingSchedule $pricingSchedule,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function refreshDuePricingPhases(\DateTimeImmutable $now): int
    {
        $refreshed = 0;

        foreach ($this->subscriptionRepository->findPricingPhaseRefreshCandidates($now) as $subscription) {
            $this->pricingSchedule->refresh($subscription, $now);
            ++$refreshed;
        }

        if ($refreshed > 0) {
            $this->entityManager->flush();
        }

        return $refreshed;
    }
}
