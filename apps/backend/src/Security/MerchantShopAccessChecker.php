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
    }
}
