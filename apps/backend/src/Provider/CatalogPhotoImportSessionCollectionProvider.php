<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CatalogPhotoImportSessionCollectionOutput;
use App\Mapper\CatalogPhotoImportSessionMapper;
use App\Repository\CatalogPhotoImportSessionRepository;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProviderInterface<CatalogPhotoImportSessionCollectionOutput>
 */
final readonly class CatalogPhotoImportSessionCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private CatalogPhotoImportSessionRepository $sessionRepository,
        private CatalogPhotoImportSessionMapper $mapper,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CatalogPhotoImportSessionCollectionOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $shop = $this->accessResolver->resolveShop($storeId);

        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'CATALOG_PHOTO_IMPORT_SESSION_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'CATALOG_PHOTO_IMPORT_SESSION_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $sessions = $this->sessionRepository->findForShop($shop, $limit, $offset);
        $total = $this->sessionRepository->countForShop($shop);

        return new CatalogPhotoImportSessionCollectionOutput(
            id: $storeId,
            items: array_map($this->mapper->toOutput(...), $sessions),
            page: $page,
            limit: $limit,
            total: $total,
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        return $value;
    }
}
