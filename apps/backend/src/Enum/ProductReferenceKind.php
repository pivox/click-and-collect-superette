<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductReferenceKind: string
{
    /** Exact industrial SKU: brand + format (+ optional GTIN). */
    case Industrial = 'industrial';

    /** Shared generic product (tomate, baguette, œuf…): no brand, no GTIN. */
    case Generic = 'generic';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
