<?php

declare(strict_types=1);

namespace App\Billing;

final readonly class MerchantPaymentReminderContext
{
    public function __construct(
        public string $merchantEmail,
        public string $merchantName,
        public string $shopName,
        public \DateTimeImmutable $dueDate,
        public string $amountTnd,
        public PaymentReminderStage $stage,
    ) {
    }
}
