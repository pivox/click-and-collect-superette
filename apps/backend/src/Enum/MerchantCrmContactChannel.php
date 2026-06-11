<?php

declare(strict_types=1);

namespace App\Enum;

enum MerchantCrmContactChannel: string
{
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Visit = 'visit';
    case Email = 'email';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $channel): string => $channel->value,
            self::cases(),
        );
    }
}
