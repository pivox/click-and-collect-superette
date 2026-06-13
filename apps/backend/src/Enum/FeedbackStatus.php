<?php

declare(strict_types=1);

namespace App\Enum;

enum FeedbackStatus: string
{
    case Unread = 'unread';
    case Read = 'read';
    case Resolved = 'resolved';
}
