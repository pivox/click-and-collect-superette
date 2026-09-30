<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\MerchantCrmProfileRepository;
use App\Repository\MerchantMembershipRepository;
use App\Repository\ShopRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * MERCHANT-TEAM-001 backfill phase: gives every historical merchant account
 * exactly one organization with an active membership, and attaches its shops.
 *
 * Idempotent by design: rerunning it never creates duplicates. Orphan shops
 * (no owner) are reported, never attached arbitrarily.
 */
final readonly class MerchantOrganizationBackfiller
{
    public function __construct(
        private UserRepository $userRepository,
        private ShopRepository $shopRepository,
        private MerchantMembershipRepository $membershipRepository,
        private SubscriptionRepository $subscriptionRepository,
        private MerchantCrmProfileRepository $crmProfileRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function backfill(): MerchantOrganizationBackfillReport
    {
        $report = new MerchantOrganizationBackfillReport();

        foreach ($this->userRepository->findAll() as $user) {
            if (!\in_array('ROLE_MERCHANT', $user->getRoles(), true)) {
                continue;
            }
            ++$report->merchantsSeen;

            $organization = $this->resolveOrganization($user, $report);
            $this->attachOwnedShops($user, $organization, $report);
            $this->attachCommercialData($user, $organization, $report);
        }

        foreach ($this->shopRepository->findBy(['owner' => null, 'merchantOrganization' => null]) as $shop) {
            ++$report->orphanShops;
            $report->orphanShopIds[] = $shop->getId()->toRfc4122();
        }

        $this->entityManager->flush();

        return $report;
    }

    private function resolveOrganization(User $merchant, MerchantOrganizationBackfillReport $report): MerchantOrganization
    {
        $memberships = $this->membershipRepository->findByUser($merchant);
        if ([] !== $memberships) {
            $organization = $memberships[0]->getOrganization();
            if (null !== $organization) {
                return $organization;
            }
        }

        $organization = (new MerchantOrganization())
            ->setName($this->organizationName($merchant))
            ->setPrimaryAccount($merchant)
            ->setActive(true);
        $this->entityManager->persist($organization);
        ++$report->organizationsCreated;

        $membership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($merchant)
            ->activate();
        $this->entityManager->persist($membership);
        ++$report->membershipsCreated;

        return $organization;
    }

    private function attachOwnedShops(User $merchant, MerchantOrganization $organization, MerchantOrganizationBackfillReport $report): void
    {
        /* @var list<Shop> */
        $shops = $this->shopRepository->findBy(['owner' => $merchant]);
        foreach ($shops as $shop) {
            ++$report->shopsSeen;
            if (null !== $shop->getMerchantOrganization()) {
                continue;
            }
            $shop->setMerchantOrganization($organization);
            ++$report->shopsAttached;
        }
    }

    /**
     * MERCHANT-TEAM-002: the subscription and CRM profile of the historical
     * merchant become the organization's commercial data. Business UUIDs,
     * amounts, periods and statuses are untouched — documents, payments and
     * reminders follow transitively through their Subscription FK.
     */
    private function attachCommercialData(User $merchant, MerchantOrganization $organization, MerchantOrganizationBackfillReport $report): void
    {
        $subscription = $this->subscriptionRepository->findOneByMerchant($merchant);
        if (null !== $subscription && null === $subscription->getMerchantOrganization()) {
            $subscription->setMerchantOrganization($organization);
            ++$report->subscriptionsAttached;
        }

        $crmProfile = $this->crmProfileRepository->findOneByMerchant($merchant);
        if (null !== $crmProfile && null === $crmProfile->getMerchantOrganization()) {
            $crmProfile->setMerchantOrganization($organization);
            ++$report->crmProfilesAttached;
        }
    }

    private function organizationName(User $merchant): string
    {
        $name = trim((string) $merchant->getName());
        if ('' !== $name) {
            return mb_substr($name, 0, 160);
        }

        return mb_substr($merchant->getEmail(), 0, 160);
    }
}
