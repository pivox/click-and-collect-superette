<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

final class LoginUserChecker extends DeletedUserChecker
{
    public function checkPreAuth(UserInterface $user): void
    {
        parent::checkPreAuth($user);

        if (!$user instanceof User) {
            return;
        }

        if (
            \in_array('ROLE_MERCHANT', $user->getRoles(), true)
            && $user->isTemporaryPasswordExpired()
        ) {
            throw new CustomUserMessageAuthenticationException('MERCHANT_TEMPORARY_PASSWORD_EXPIRED');
        }
    }
}
