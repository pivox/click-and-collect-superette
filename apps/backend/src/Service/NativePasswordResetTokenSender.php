<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

final readonly class NativePasswordResetTokenSender implements PasswordResetTokenSenderInterface
{
    public function send(User $user, string $rawToken): void
    {
        $subject = 'Réinitialisation de votre mot de passe Kadhia';
        $body = \sprintf(
            "Bonjour,\n\nUtilisez ce token pour réinitialiser votre mot de passe : %s\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            $rawToken,
        );

        @mail($user->getEmail(), $subject, $body);
    }
}
