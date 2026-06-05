<?php

declare(strict_types=1);

namespace App\Service;

use App\Email\TransactionalEmail;
use App\Email\TransactionalEmailSenderInterface;
use App\Entity\User;

final readonly class PasswordResetTokenEmailSender implements PasswordResetTokenSenderInterface
{
    public function __construct(
        private string $frontendUrl,
        private TransactionalEmailSenderInterface $emailSender,
    ) {
    }

    public function send(User $user, string $rawToken): void
    {
        $resetUrl = rtrim($this->frontendUrl, '/').'/reset-password?token='.urlencode($rawToken);
        $body = \sprintf(
            "Bonjour,\n\nCliquez sur ce lien pour réinitialiser votre mot de passe :\n%s\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            $resetUrl,
        );

        $this->emailSender->send(new TransactionalEmail(
            recipients: [$user->getEmail()],
            subject: 'Réinitialisation de votre mot de passe Kadhia',
            text: $body,
        ));
    }
}
