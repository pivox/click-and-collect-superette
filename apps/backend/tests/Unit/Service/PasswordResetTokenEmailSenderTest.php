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
        // Customers use the default login: no portal hint in the link.
        self::assertStringNotContainsString('portal=', $sender->sent[0]->text);
    }

    public function testAppendsMerchantPortalToResetLink(): void
    {
        $sender = new CapturingTransactionalEmailSender();
        $user = (new User())
            ->setEmail('merchant@example.test')
            ->setName('Merchant Test')
            ->setRoles(['ROLE_MERCHANT'])
            ->setPassword('secret');

        (new PasswordResetTokenEmailSender('https://app.kadhia.test', $sender))->send($user, 'tok');

        self::assertStringContainsString('/reset-password?token=tok&portal=merchant', $sender->sent[0]->text);
    }

    public function testAppendsAdminPortalToResetLink(): void
    {
        $sender = new CapturingTransactionalEmailSender();
        $user = (new User())
            ->setEmail('admin@example.test')
            ->setName('Admin Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('secret');

        (new PasswordResetTokenEmailSender('https://app.kadhia.test', $sender))->send($user, 'tok');

        self::assertStringContainsString('/reset-password?token=tok&portal=admin', $sender->sent[0]->text);
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
