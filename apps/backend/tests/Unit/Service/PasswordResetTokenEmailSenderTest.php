<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Email\TransactionalEmail;
use App\Email\TransactionalEmailSenderInterface;
use App\Entity\User;
use App\Service\PasswordResetTokenEmailSender;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenEmailSenderTest extends TestCase
{
    public function testSendsResetLinkThroughTransactionalEmailSender(): void
    {
        $sender = new CapturingTransactionalEmailSender();
        $user = (new User())
            ->setEmail('client@example.test')
            ->setName('Client Test')
            ->setPassword('secret');

        (new PasswordResetTokenEmailSender('https://app.kadhia.test', $sender))->send($user, 'raw token + symbols');

        self::assertCount(1, $sender->sent);
        self::assertSame(['client@example.test'], $sender->sent[0]->recipients);
        self::assertStringContainsString('Réinitialisation', $sender->sent[0]->subject);
        self::assertStringContainsString(
            'https://app.kadhia.test/reset-password?token=raw+token+%2B+symbols',
            $sender->sent[0]->text,
        );
    }
}

final class CapturingTransactionalEmailSender implements TransactionalEmailSenderInterface
{
    /** @var list<TransactionalEmail> */
    public array $sent = [];

    public function send(TransactionalEmail $email): void
    {
        $this->sent[] = $email;
    }
}
