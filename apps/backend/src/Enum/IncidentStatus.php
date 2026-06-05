<?php

declare(strict_types=1);

namespace App\Enum;

enum IncidentStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';
}
