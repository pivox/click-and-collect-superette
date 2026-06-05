<?php

declare(strict_types=1);

namespace App\Enum;

enum BillingDocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
