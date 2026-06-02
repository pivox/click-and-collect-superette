<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

final readonly class NativePasswordResetTokenSender implements PasswordResetTokenSenderInterface
{
    public function __construct(private string $frontendUrl)
    {
    }

    public function send(User $user, string $rawToken): void
    {
        $resetUrl = rtrim($this->frontendUrl, '/').'/reset-password?token='.urlencode($rawToken);
        $subject = 'Réinitialisation de votre mot de passe Kadhia';
        $body = \sprintf(
            "Bonjour,\n\nCliquez sur ce lien pour réinitialiser votre mot de passe :\n%s\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            $resetUrl,
        );

        @mail($user->getEmail(), $subject, $body);
    }
}
