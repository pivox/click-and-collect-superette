<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionLifecycle: string
{
    case Active = 'active';
    case PaymentDue = 'payment_due';
    case GracePeriod = 'grace_period';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}
