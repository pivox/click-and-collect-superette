<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceDuplicateCandidateOutput;
use App\ApiResource\AdminProductReferenceDuplicateListOutput;
use App\Repository\ProductImageRepository;
use App\Service\ProductImage\ProductImageUrlBuilder;
use App\Service\ProductReferenceDeduplicationService;
use App\Service\ProductReferenceQualityScorer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<AdminProductReferenceDuplicateListOutput>
 */
final readonly class AdminProductReferenceDuplicateCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private ProductReferenceDeduplicationService $deduplicationService,
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
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceDuplicateListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PRODUCT_REFERENCE_DUPLICATES_INVALID_PAGE');
        $limit = min(self::MAX_LIMIT, $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PRODUCT_REFERENCE_DUPLICATES_INVALID_LIMIT'));
        $offset = ($page - 1) * $limit;

        $candidates = $this->deduplicationService->findDuplicateCandidates();
        $pagedCandidates = \array_slice($candidates, $offset, $limit);
        $candidateReferences = [];
        foreach ($pagedCandidates as $candidate) {
            $candidateReferences[] = $candidate['left'];
            $candidateReferences[] = $candidate['right'];
        }
        $officialImages = $this->productImageRepository->findOfficialByProductReferences($candidateReferences);
        $items = array_map(
            fn (array $candidate): AdminProductReferenceDuplicateCandidateOutput => new AdminProductReferenceDuplicateCandidateOutput(
                left: AdminProductReferenceItemProvider::toOutput(
                    $candidate['left'],
                    $this->productImageUrlBuilder->build($officialImages[$candidate['left']->getId()->toRfc4122()] ?? null),
                    $this->qualityScorer,
                ),
                right: AdminProductReferenceItemProvider::toOutput(
                    $candidate['right'],
                    $this->productImageUrlBuilder->build($officialImages[$candidate['right']->getId()->toRfc4122()] ?? null),
                    $this->qualityScorer,
                ),
                reason: $candidate['reason'],
                barcode: $candidate['barcode'],
                leftOfferCount: $candidate['leftOfferCount'],
                rightOfferCount: $candidate['rightOfferCount'],
            ),
            $pagedCandidates,
        );

        return new AdminProductReferenceDuplicateListOutput(
            id: 'admin-product-reference-duplicates',
            items: $items,
            page: $page,
            limit: $limit,
            total: \count($candidates),
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
}
