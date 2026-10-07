<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CatalogPhotoImportQuotaOutput;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use App\Service\CatalogPhotoQuotaLedger;

/**
 * @implements ProviderInterface<CatalogPhotoImportQuotaOutput>
 */
final readonly class CatalogPhotoImportQuotaProvider implements ProviderInterface
{
    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private CatalogPhotoQuotaLedger $quotaLedger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CatalogPhotoImportQuotaOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $shop = $this->accessResolver->resolveShop($storeId);
        $balance = $this->quotaLedger->getBalance($shop);

        return new CatalogPhotoImportQuotaOutput(
            storeId: $storeId,
            granted: $balance->granted,
            reserved: $balance->reserved,
            consumed: $balance->consumed,
            available: $balance->available,
        );
    }
}
