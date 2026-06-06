<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceComparisonOutput;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductImageRepository;
use App\Service\ProductImage\ProductImageUrlBuilder;
use App\Service\ProductReferenceDeduplicationService;
use App\Service\ProductReferenceQualityScorer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminProductReferenceComparisonOutput>
 */
final readonly class AdminProductReferenceComparisonProvider implements ProviderInterface
{
    public function __construct(
        private AdminProductReferenceRepository $productReferenceRepository,
        private ProductImageRepository $productImageRepository,
        private ProductImageUrlBuilder $productImageUrlBuilder,
        private ProductReferenceQualityScorer $qualityScorer,
        private ProductReferenceDeduplicationService $deduplicationService,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceComparisonOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $leftId = $request?->query->getString('left') ?? '';
        $rightId = $request?->query->getString('right') ?? '';

        if (!Uuid::isValid($leftId) || !Uuid::isValid($rightId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $left = $this->productReferenceRepository->findOne($leftId);
        $right = $this->productReferenceRepository->findOne($rightId);
        if (null === $left || null === $right) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $officialImages = $this->productImageRepository->findOfficialByProductReferences([$left, $right]);

        return new AdminProductReferenceComparisonOutput(
            id: $leftId.'-'.$rightId,
            left: AdminProductReferenceItemProvider::toOutput(
                $left,
                $this->productImageUrlBuilder->build($officialImages[$left->getId()->toRfc4122()] ?? null),
                $this->qualityScorer,
            ),
            right: AdminProductReferenceItemProvider::toOutput(
                $right,
                $this->productImageUrlBuilder->build($officialImages[$right->getId()->toRfc4122()] ?? null),
                $this->qualityScorer,
            ),
            leftOfferCount: $this->deduplicationService->countMerchantOffers($left),
            rightOfferCount: $this->deduplicationService->countMerchantOffers($right),
            reason: $this->deduplicationService->matchReason($left, $right),
        );
    }
}
