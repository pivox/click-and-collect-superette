<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Shop;
use App\Entity\User;
use App\Security\MerchantShopAccessChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * MERCHANT-TEAM-003: delegates the relationship rule to the central checker
 * (active membership on the shop organization, historical owner fallback).
 * The attribute name is kept for compatibility with existing call sites.
 *
 * @extends Voter<string, Shop>
 */
final class ShopOwnerVoter extends Voter
{
    public const SHOP_OWNER = 'SHOP_OWNER';

    public function __construct(
        private readonly Security $security,
        private readonly MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::SHOP_OWNER === $attribute && $subject instanceof Shop;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof Shop) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $this->merchantShopAccessChecker->canOperateShop($user, $subject);
    }
}
