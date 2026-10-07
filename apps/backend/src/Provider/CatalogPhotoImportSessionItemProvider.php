<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CatalogPhotoImportSessionOutput;
use App\Mapper\CatalogPhotoImportSessionMapper;
use App\Service\CatalogPhotoImportSessionAccessResolver;

/**
 * @implements ProviderInterface<CatalogPhotoImportSessionOutput>
 */
final readonly class CatalogPhotoImportSessionItemProvider implements ProviderInterface
{
    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private CatalogPhotoImportSessionMapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CatalogPhotoImportSessionOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $sessionId = (string) ($uriVariables['sessionId'] ?? '');
        $session = $this->accessResolver->resolveSession($storeId, $sessionId);

        return $this->mapper->toOutput($session);
    }
}
