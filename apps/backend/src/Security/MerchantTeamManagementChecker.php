<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * MERCHANT-TEAM-004: team lifecycle endpoints are reserved to the primary
 * account of the shop organization. The primary status is fixed and grants
 * no other operational privilege.
 */
final readonly class MerchantTeamManagementChecker
{
    public function __construct(
        private Security $security,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @return array{organization: MerchantOrganization, actor: User}
     */
    public function denyUnlessPrimaryAccountOfShop(Shop $shop): array
    {
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $actor = $this->security->getUser();
        $organization = $shop->getMerchantOrganization();

        // A shop without organization has no manageable team (run the backfill).
        if (!$actor instanceof User
            || null === $organization
            || true !== $organization->getPrimaryAccount()?->getId()->equals($actor->getId())
        ) {
            throw new AccessDeniedHttpException('MERCHANT_TEAM_MANAGEMENT_FORBIDDEN');
        }

        return ['organization' => $organization, 'actor' => $actor];
    }
}
