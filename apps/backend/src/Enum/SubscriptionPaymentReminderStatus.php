<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPaymentReminderStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Contacted = 'contacted';
}
