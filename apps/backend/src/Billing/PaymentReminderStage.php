<?php

declare(strict_types=1);

namespace App\Billing;

enum PaymentReminderStage: string
{
    case BeforeDueDate = 'j_minus_7';
    case DueDate = 'j';
    case GracePeriod7 = 'j_plus_7';
    case GracePeriod14 = 'j_plus_14';
    case SuspensionWarning21 = 'j_plus_21';
}
