<?php

declare(strict_types=1);

namespace App\Tests\Support\MerchantInvitation;

use App\Entity\User;
use App\Service\MerchantInvitationSenderInterface;

final class TestMerchantInvitationSender implements MerchantInvitationSenderInterface
{
    /**
     * @var array<string, string>
     */
    private array $tokensByEmail = [];

    public function send(User $merchant, string $rawToken, \DateTimeImmutable $expiresAt): void
    {
        $this->tokensByEmail[$merchant->getEmail()] = $rawToken;
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
