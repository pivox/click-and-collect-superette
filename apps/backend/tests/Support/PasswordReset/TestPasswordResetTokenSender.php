<?php

declare(strict_types=1);

namespace App\Tests\Support\PasswordReset;

use App\Entity\User;
use App\Service\PasswordResetTokenSenderInterface;

final class TestPasswordResetTokenSender implements PasswordResetTokenSenderInterface
{
    /**
     * @var array<string, string>
     */
    private array $tokensByEmail = [];

    public function send(User $user, string $rawToken): void
    {
        $this->tokensByEmail[$user->getEmail()] = $rawToken;
    }

    public function tokenFor(string $email): ?string
    {
        return $this->tokensByEmail[$email] ?? null;
    }

    public function reset(): void
    {
        $this->tokensByEmail = [];
    }
}
