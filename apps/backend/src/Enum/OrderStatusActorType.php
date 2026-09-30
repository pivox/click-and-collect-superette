<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatusActorType: string
{
    case Customer = 'customer';
    case Merchant = 'merchant';
    case Admin = 'admin';
    case System = 'system';
}
