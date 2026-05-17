<?php

declare(strict_types=1);

namespace App\Processor;

use App\ApiResource\OrderOutput;
use App\Entity\Order;

final readonly class SubmittedOrderResult
{
    public function __construct(
        public Order $order,
        public OrderOutput $output,
    ) {
    }
}
