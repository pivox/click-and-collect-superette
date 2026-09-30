<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Entity\User;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantMembershipRepository;

/**
 * MERCHANT-TEAM-005: resolves which merchant accounts receive an operational
 * notification for a shop.
 *
 * Organization path: every active User holding an active membership of the
 * shop's active organization (the primary account has no priority). Shops not
 * yet backfilled fall back to their active owner. Revoked or invited
 * memberships and inactive users are excluded before any dispatch.
 */
final readonly class MerchantNotificationRecipientResolver
{
    public function __construct(
        private MerchantMembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @return list<User>
     */
    public function resolveForShop(Shop $shop): array
    {
        $organization = $shop->getMerchantOrganization();

        if (null === $organization) {
            $owner = $shop->getOwner();

            return null !== $owner && $owner->isActive() ? [$owner] : [];
        }

        if (!$organization->isActive()) {
            return [];
        }

        $memberships = $this->membershipRepository->findBy([
            'organization' => $organization,
            'status' => MerchantMembershipStatus::Active,
        ]);

        $recipients = [];
        foreach ($memberships as $membership) {
            $user = $membership->getUser();
            if (null === $user || !$user->isActive()) {
                continue;
            }
            $recipients[$user->getId()->toRfc4122()] = $user;
        }

        return array_values($recipients);
    }
}
