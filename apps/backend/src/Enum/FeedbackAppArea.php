<?php

declare(strict_types=1);

namespace App\Enum;

enum FeedbackAppArea: string
{
    case Client = 'client';
    case Merchant = 'merchant';
    case Admin = 'admin';
}
