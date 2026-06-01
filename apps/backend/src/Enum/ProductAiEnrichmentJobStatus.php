<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductAiEnrichmentJobStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Succeeded = 'succeeded';
    case Applied = 'applied';
    case Failed = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
