<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailSenderInterface;
use App\Billing\PaymentReminderStage;
use App\Message\SendMerchantPaymentReminderMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendMerchantPaymentReminderMessageHandler
{
    public function __construct(private MerchantPaymentReminderEmailSenderInterface $sender)
    {
    }

    public function __invoke(SendMerchantPaymentReminderMessage $message): void
    {
        $this->sender->send(new MerchantPaymentReminderContext(
            merchantEmail: $message->merchantEmail,
            merchantName: $message->merchantName,
            shopName: $message->shopName,
            dueDate: new \DateTimeImmutable($message->dueDate),
            amountTnd: $message->amountTnd,
            stage: PaymentReminderStage::from($message->stage),
        ));
    }
}
