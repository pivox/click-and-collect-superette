<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Shop;
use App\Entity\User;
use App\Repository\MerchantMembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Single choke point for merchant access to a shop (MERCHANT-TEAM-003).
 *
 * Target rule: ROLE_MERCHANT + active User + active membership + active
 * organization + shop belonging to the same organization. Membership state is
 * read on every request, so a revocation takes effect on the next call even
 * with a still-valid JWT.
 *
 * Transition fallback: a shop not yet backfilled (no organization) keeps the
 * historical owner rule. The method name is kept so the 60+ existing call
 * sites stay untouched; semantically it now means "can operate the shop".
 *
 * The response deliberately uses the same 403 code for foreign organization,
 * missing/invited/revoked membership and inactive organization, so no
 * information leaks about why access is denied.
 */
final readonly class MerchantShopAccessChecker
{
    public function __construct(
        private Security $security,
        private MerchantMembershipRepository $membershipRepository,
    ) {
    }

    public function denyUnlessMerchantOwnsShop(Shop $shop): void
    {
        if (!$this->security->isGranted('ROLE_MERCHANT')) {
            throw new AccessDeniedHttpException('MERCHANT_CATALOG_FORBIDDEN');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->canOperateShop($user, $shop)) {
            throw new AccessDeniedHttpException('MERCHANT_CATALOG_FORBIDDEN');
        }

        // A suspended merchant keeps a valid JWT until expiry (DeletedUserChecker
        // only rejects deleted accounts, not inactive ones). Mirror the guard
        // MerchantMeProvider already applies so every merchant mutation/read going
        // through this single choke point is blocked while the account is suspended.
        if (!$user->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }
    }

    /**
     * Relationship check only — account activity is enforced separately so the
     * dedicated MERCHANT_ACCOUNT_INACTIVE code is preserved.
     */
    public function canOperateShop(User $user, Shop $shop): bool
    {
        $organization = $shop->getMerchantOrganization();
        if (null !== $organization) {
            if (!$organization->isActive()) {
                return false;
            }

            $membership = $this->membershipRepository->findOneActiveByUser($user);

            return null !== $membership
                && true === $membership->getOrganization()?->getId()->equals($organization->getId());
        }

        $owner = $shop->getOwner();

        return null !== $owner && $owner->getId()->equals($user->getId());
    }
}
