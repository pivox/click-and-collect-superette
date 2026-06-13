<?php

declare(strict_types=1);

namespace App\Enum;

enum FeedbackType: string
{
    case Bug = 'bug';
    case Idea = 'idea';
    case Confusing = 'confusing';
    case Other = 'other';
}
