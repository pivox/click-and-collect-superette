<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendMerchantPaymentReminderMessage
{
    public function __construct(
        public string $billingDocumentId,
        public string $documentNumber,
        public string $merchantEmail,
        public string $merchantName,
        public string $shopName,
        public string $dueDate,
        public string $amountTnd,
        public string $stage,
    ) {
    }
}
