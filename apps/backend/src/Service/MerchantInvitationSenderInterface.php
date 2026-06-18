<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

interface MerchantInvitationSenderInterface
{
    public function send(User $merchant, string $rawToken, \DateTimeImmutable $expiresAt): void;
}
