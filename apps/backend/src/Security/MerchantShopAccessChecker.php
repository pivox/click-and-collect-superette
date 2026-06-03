<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Shop;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class MerchantShopAccessChecker
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function denyUnlessMerchantOwnsShop(Shop $shop): void
    {
        if (!$this->security->isGranted('ROLE_MERCHANT')) {
            throw new AccessDeniedHttpException('MERCHANT_CATALOG_FORBIDDEN');
        }

        $user = $this->security->getUser();
        $owner = $shop->getOwner();

        if (!$user instanceof User || null === $owner || !$owner->getId()->equals($user->getId())) {
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
}
