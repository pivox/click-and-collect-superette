<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailSenderInterface;
use App\Billing\PaymentReminderStage;
use App\Message\SendMerchantPaymentReminderMessage;
use App\MessageHandler\SendMerchantPaymentReminderMessageHandler;
use PHPUnit\Framework\TestCase;

final class SendMerchantPaymentReminderMessageHandlerTest extends TestCase
{
    public function testHandlerBuildsReminderContextAndSendsEmail(): void
    {
        $sender = new CapturingMerchantPaymentReminderEmailSender();
        $handler = new SendMerchantPaymentReminderMessageHandler($sender);

        $handler(new SendMerchantPaymentReminderMessage(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: '2026-06-11',
            amountTnd: '50.000',
            stage: 'j_plus_7',
        ));

        self::assertCount(1, $sender->sent);
        self::assertSame('marchand@example.test', $sender->sent[0]->merchantEmail);
        self::assertSame('Supérette El Amal', $sender->sent[0]->shopName);
        self::assertSame('50.000', $sender->sent[0]->amountTnd);
        self::assertEquals(new \DateTimeImmutable('2026-06-11'), $sender->sent[0]->dueDate);
        self::assertSame(PaymentReminderStage::GracePeriod7, $sender->sent[0]->stage);
    }
}

final class CapturingMerchantPaymentReminderEmailSender implements MerchantPaymentReminderEmailSenderInterface
{
    /** @var list<MerchantPaymentReminderContext> */
    public array $sent = [];

    public function send(MerchantPaymentReminderContext $context): void
    {
        $this->sent[] = $context;
    }
}
