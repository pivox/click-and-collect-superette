<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPricingPhase: string
{
    case Trial = 'trial';
    case Promo = 'promo';
    case Standard = 'standard';
}
