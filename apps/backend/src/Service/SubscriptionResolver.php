<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\MerchantMembershipRepository;
use App\Repository\SubscriptionRepository;

/**
 * MERCHANT-TEAM-002: resolves the commercial subscription during the
 * organization transition.
 *
 * Preference order: the organization's subscription, then the historical
 * merchant-User attachment as fallback. Once every subscription is backfilled
 * and secondary accounts exist, all accounts and shops of one organization
 * resolve the same single subscription.
 */
final readonly class SubscriptionResolver
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private MerchantMembershipRepository $membershipRepository,
    ) {
    }

    public function forMerchantUser(User $user): ?Subscription
    {
        $membership = $this->membershipRepository->findOneActiveByUser($user);
        $organization = $membership?->getOrganization();
        if (null !== $organization) {
            $subscription = $this->subscriptionRepository->findOneByOrganization($organization);
            if (null !== $subscription) {
                return $subscription;
            }
        }

        return $this->subscriptionRepository->findOneByMerchant($user);
    }

    public function forShop(Shop $shop): ?Subscription
    {
        $organization = $shop->getMerchantOrganization();
        if (null !== $organization) {
            $subscription = $this->subscriptionRepository->findOneByOrganization($organization);
            if (null !== $subscription) {
                return $subscription;
            }
        }

        $owner = $shop->getOwner();

        return null === $owner ? null : $this->subscriptionRepository->findOneByMerchant($owner);
    }
}
