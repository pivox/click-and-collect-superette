<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductUnit: string
{
    case Litre = 'litre';
    case Millilitre = 'millilitre';
    case Kilogramme = 'kilogramme';
    case Gramme = 'gramme';
    case Piece = 'piece';
    case Paquet = 'paquet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $unit): string => $unit->value,
            self::cases(),
        );
    }
}
