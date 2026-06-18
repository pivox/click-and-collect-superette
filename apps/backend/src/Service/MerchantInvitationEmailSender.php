<?php

declare(strict_types=1);

namespace App\Service;

use App\Email\TransactionalEmail;
use App\Email\TransactionalEmailSenderInterface;
use App\Entity\User;

final readonly class MerchantInvitationEmailSender implements MerchantInvitationSenderInterface
{
    public function __construct(
        private string $frontendUrl,
        private TransactionalEmailSenderInterface $emailSender,
    ) {
    }

    public function send(User $merchant, string $rawToken, \DateTimeImmutable $expiresAt): void
    {
        $activationUrl = rtrim($this->frontendUrl, '/').'/merchant/invitation?token='.urlencode($rawToken);
        $body = \sprintf(
            "Bonjour,\n\nUn administrateur Kadhia vous invite à définir le mot de passe de votre espace marchand.\n\nLien d'activation :\n%s\n\nCe lien expire le %s et ne peut être utilisé qu'une seule fois.\n\nSi vous n'attendiez pas cette invitation, ignorez ce message.",
            $activationUrl,
            $expiresAt->format(\DateTimeInterface::ATOM),
        );

        $this->emailSender->send(new TransactionalEmail(
            recipients: [$merchant->getEmail()],
            subject: 'Activation de votre espace marchand Kadhia',
            text: $body,
        ));
    }
}
