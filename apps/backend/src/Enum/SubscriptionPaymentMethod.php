<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
}
