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

        // Carry the portal so the post-reset screen can route the user back to
        // the right login (merchant / admin); customers use the default login.
        $portal = $this->resolvePortal($user);
        if (null !== $portal) {
            $resetUrl .= '&portal='.$portal;
        }

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

    private function resolvePortal(User $user): ?string
    {
        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'admin';
        }

        if (\in_array('ROLE_MERCHANT', $roles, true)) {
            return 'merchant';
        }

        return null;
    }
}
