<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Shop;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ShopOwnerVoter extends Voter
{
    public const SHOP_OWNER = 'SHOP_OWNER';

    public function __construct(
        private readonly Security $security,
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

        $owner = $subject->getOwner();

        if (null === $owner) {
            return false;
        }

        return $owner->getId()->equals($user->getId());
    }
}
