<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case PickupPending = 'pickup_pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
