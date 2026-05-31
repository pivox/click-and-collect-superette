<?php

declare(strict_types=1);

namespace App\Enum;

enum MerchantProductPriceSource: string
{
    case MerchantDashboard = 'merchant_dashboard';
    case AdminDashboard = 'admin_dashboard';
    case CatalogImport = 'catalog_import';
    case AiImport = 'ai_import';
    case Api = 'api';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $source): string => $source->value,
            self::cases(),
        );
    }
}
