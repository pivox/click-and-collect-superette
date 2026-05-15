<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

interface PasswordResetTokenSenderInterface
{
    public function send(User $user, string $rawToken): void;
}
