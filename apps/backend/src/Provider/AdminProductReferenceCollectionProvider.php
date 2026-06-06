<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceListOutput;
use App\Enum\ProductReferenceStatus;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductImageRepository;
use App\Service\ProductImage\ProductImageUrlBuilder;
use App\Service\ProductReferenceQualityScorer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminProductReferenceListOutput>
 */
final readonly class AdminProductReferenceCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
        private ProductImageRepository $productImageRepository,
        private ProductImageUrlBuilder $productImageUrlBuilder,
        private ProductReferenceQualityScorer $qualityScorer,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PRODUCT_REFERENCE_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PRODUCT_REFERENCE_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $q = $request?->query->getString('q') ?: null;
        $categoryId = $request?->query->getString('category') ?: null;
        $brandId = $request?->query->getString('brand') ?: null;
        $status = $request?->query->getString('status') ?: null;
        $qualityMin = $this->parseScore($request?->query->get('quality_min'), 'ADMIN_PRODUCT_REFERENCE_INVALID_QUALITY_FILTER');
        $qualityMax = $this->parseScore($request?->query->get('quality_max'), 'ADMIN_PRODUCT_REFERENCE_INVALID_QUALITY_FILTER');
        $sort = $request?->query->getString('sort') ?: null;
        $direction = strtolower($request?->query->getString('direction') ?: 'asc');

        if (null !== $brandId && !Uuid::isValid($brandId)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_REFERENCE_INVALID_BRAND_FILTER');
        }
        if (null !== $categoryId && !Uuid::isValid($categoryId)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_REFERENCE_INVALID_CATEGORY_FILTER');
        }
        if (null !== $status && null === ProductReferenceStatus::tryFrom($status)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_REFERENCE_INVALID_STATUS_FILTER');
        }
        if (null !== $qualityMin && null !== $qualityMax && $qualityMin > $qualityMax) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_REFERENCE_INVALID_QUALITY_FILTER');
        }
        if (null !== $sort && 'quality_score' !== $sort) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_REFERENCE_INVALID_SORT');
        }
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new BadRequestHttpException('ADMIN_PRODUCT_REFERENCE_INVALID_DIRECTION');
        }

        $productReferences = $this->adminProductReferenceRepository->findPaginated(
            $limit,
            $offset,
            $q,
            $categoryId,
            $brandId,
            $status,
            $qualityMin,
            $qualityMax,
            $sort,
            strtoupper($direction),
        );
        $officialImages = $this->productImageRepository->findOfficialByProductReferences($productReferences);
        $items = array_map(
            fn ($ref) => AdminProductReferenceItemProvider::toOutput(
                $ref,
                $this->productImageUrlBuilder->build($officialImages[$ref->getId()->toRfc4122()] ?? null),
                $this->qualityScorer,
            ),
            $productReferences,
        );

        return new AdminProductReferenceListOutput(
            id: 'admin-product-references',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminProductReferenceRepository->countFiltered($q, $categoryId, $brandId, $status, $qualityMin, $qualityMax),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }

    private function parseScore(mixed $raw, string $errorCode): ?int
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 0 || $value > 100) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }
}
