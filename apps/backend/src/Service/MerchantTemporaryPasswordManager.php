<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class MerchantTemporaryPasswordManager
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private int $merchantTemporaryPasswordTtl,
    ) {
    }

    public function generateFor(User $merchant, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();
        $temporaryPassword = bin2hex(random_bytes(18));

        $merchant
            ->setPassword($this->passwordHasher->hashPassword($merchant, $temporaryPassword))
            ->setPasswordChangeRequired(true)
            ->setTemporaryPasswordGeneratedAt($now)
            ->setTemporaryPasswordExpiresAt($now->modify(\sprintf('+%d seconds', $this->merchantTemporaryPasswordTtl)));

        return $temporaryPassword;
    }
}
