<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantCrmProfileRepository;
use App\Repository\MerchantMembershipRepository;
use App\Repository\MerchantOrganizationRepository;
use App\Repository\ShopRepository;
use App\Repository\SubscriptionRepository;

/**
 * MERCHANT-TEAM-001 diagnostic: detects data anomalies in the
 * organization/membership model. It never repairs anything.
 */
final readonly class MerchantOrganizationAuditor
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantOrganizationRepository $organizationRepository,
        private MerchantMembershipRepository $membershipRepository,
        private SubscriptionRepository $subscriptionRepository,
        private MerchantCrmProfileRepository $crmProfileRepository,
    ) {
    }

    /**
     * Anomalies are data inconsistencies that must fail the audit; warnings are
     * legitimate transient states reported for visibility only.
     *
     * @return array{anomalies: list<string>, warnings: list<string>}
     */
    public function audit(): array
    {
        $anomalies = [];
        $warnings = [];

        foreach ($this->shopRepository->findBy(['active' => true, 'merchantOrganization' => null]) as $shop) {
            $anomalies[] = \sprintf('active_shop_without_organization: %s', $shop->getId()->toRfc4122());
        }

        $organizations = $this->organizationRepository->findAll();
        $memberships = $this->membershipRepository->findAll();

        $activeMembershipsByUser = [];
        $membershipPairs = [];
        foreach ($memberships as $membership) {
            $userId = $membership->getUser()?->getId()->toRfc4122() ?? 'null';
            $orgId = $membership->getOrganization()?->getId()->toRfc4122() ?? 'null';

            $pairKey = $orgId.'/'.$userId;
            $membershipPairs[$pairKey] = ($membershipPairs[$pairKey] ?? 0) + 1;
            if (2 === $membershipPairs[$pairKey]) {
                $anomalies[] = \sprintf('duplicate_membership_for_pair: %s', $pairKey);
            }

            if (MerchantMembershipStatus::Active === $membership->getStatus()) {
                $activeMembershipsByUser[$userId][] = $membership;

                $user = $membership->getUser();
                if (null !== $user && !\in_array('ROLE_MERCHANT', $user->getRoles(), true)) {
                    $anomalies[] = \sprintf('active_membership_without_merchant_role: %s', $userId);
                }
            }
        }

        foreach ($activeMembershipsByUser as $userId => $userMemberships) {
            $organizationIds = array_unique(array_map(
                static fn (MerchantMembership $m): string => $m->getOrganization()?->getId()->toRfc4122() ?? 'null',
                $userMemberships,
            ));
            if (\count($organizationIds) > 1) {
                $anomalies[] = \sprintf('user_in_multiple_active_organizations: %s', $userId);
            }
        }

        foreach ($organizations as $organization) {
            $orgId = $organization->getId()->toRfc4122();
            $primaryAccount = $organization->getPrimaryAccount();

            if (null === $primaryAccount) {
                $anomalies[] = \sprintf('organization_without_primary_account: %s', $orgId);
            } elseif (!$this->hasActiveMembership($organization, $primaryAccount->getId()->toRfc4122(), $memberships)) {
                $anomalies[] = \sprintf('primary_account_without_active_membership: %s', $orgId);
            }

            $shops = $this->shopRepository->findBy(['merchantOrganization' => $organization]);
            if ([] === $shops) {
                // Legitimate transient state: an organization can exist before
                // its first shop is created. Reported, but never blocking.
                $warnings[] = \sprintf('organization_without_shop: %s', $orgId);
            }

            foreach ($shops as $shop) {
                $owner = $shop->getOwner();
                if (null !== $owner && null !== $primaryAccount && !$owner->getId()->equals($primaryAccount->getId())) {
                    $anomalies[] = \sprintf(
                        'shop_owner_diverges_from_primary_account: shop=%s',
                        $shop->getId()->toRfc4122(),
                    );
                }
            }
        }

        // MERCHANT-TEAM-002: commercial data still detached from its
        // organization is a transition anomaly (run the backfill).
        foreach ($this->subscriptionRepository->findAll() as $subscription) {
            if (null === $subscription->getMerchantOrganization()) {
                $anomalies[] = \sprintf('subscription_without_organization: %s', $subscription->getId()->toRfc4122());
            }
        }
        foreach ($this->crmProfileRepository->findAll() as $profile) {
            if (null === $profile->getMerchantOrganization()) {
                $anomalies[] = \sprintf('crm_profile_without_organization: %s', $profile->getId()->toRfc4122());
            }
        }

        return ['anomalies' => $anomalies, 'warnings' => $warnings];
    }

    /**
     * @param list<MerchantMembership> $memberships
     */
    private function hasActiveMembership(MerchantOrganization $organization, string $userId, array $memberships): bool
    {
        foreach ($memberships as $membership) {
            if (MerchantMembershipStatus::Active !== $membership->getStatus()) {
                continue;
            }
            if (true !== $membership->getOrganization()?->getId()->equals($organization->getId())) {
                continue;
            }
            if ($membership->getUser()?->getId()->toRfc4122() === $userId) {
                return true;
            }
        }

        return false;
    }
}
