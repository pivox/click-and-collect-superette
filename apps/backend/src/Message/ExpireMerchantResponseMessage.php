<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ExpireMerchantResponseMessage
{
    public function __construct(
        public string $orderId,
    ) {
    }
}
