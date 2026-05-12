<?php

declare(strict_types=1);

namespace App\Enum;

enum KadhiaStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
