<?php

declare(strict_types=1);

namespace App\Email;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonyMailerTransactionalEmailSender implements TransactionalEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFromEmail,
        private string $mailerFromName,
    ) {
    }

    public function send(TransactionalEmail $email): void
    {
        $message = (new Email())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->subject($email->subject)
            ->text($email->text);

        foreach ($email->recipients as $recipient) {
            $message->addTo($recipient);
        }

        $this->mailer->send($message);
    }
}
