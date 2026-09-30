<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Enum\SubscriptionLifecycle;
use App\Repository\SubscriptionRepository;

final readonly class ShopOrderingAvailabilityChecker
{
    public const STORE_NOT_AVAILABLE = 'STORE_NOT_AVAILABLE';
    public const STORE_SUSPENDED_FOR_SUBSCRIPTION = 'STORE_SUSPENDED_FOR_SUBSCRIPTION';

    public function __construct(
        private ?SubscriptionRepository $subscriptionRepository = null,
        // MERCHANT-TEAM-002: preferred resolution (organization first, owner
        // fallback). The legacy repository path is kept for unit tests that
        // build the checker without services.
        private ?SubscriptionResolver $subscriptionResolver = null,
    ) {
    }

    public function acceptsNewKadhias(Shop $shop): bool
    {
        return null === $this->blockReason($shop);
    }

    public function blockReason(Shop $shop): ?string
    {
        if (!$shop->isActive() || null !== $shop->getArchivedAt()) {
            return self::STORE_NOT_AVAILABLE;
        }

        $owner = $shop->getOwner();
        if (null !== $owner && !$owner->isActive()) {
            return self::STORE_SUSPENDED_FOR_SUBSCRIPTION;
        }

        $subscription = null;
        if (null !== $this->subscriptionResolver) {
            $subscription = $this->subscriptionResolver->forShop($shop);
        } elseif (null !== $owner && null !== $this->subscriptionRepository) {
            $subscription = $this->subscriptionRepository->findOneByMerchant($owner);
        }
        if (SubscriptionLifecycle::Suspended === $subscription?->getLifecycle()) {
            return self::STORE_SUSPENDED_FOR_SUBSCRIPTION;
        }

        return null;
    }
}
