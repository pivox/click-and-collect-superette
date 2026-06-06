<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceComparisonOutput;
use App\Repository\AdminProductReferenceRepository;
use App\Service\ProductReferenceDeduplicationService;
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

        return new AdminProductReferenceComparisonOutput(
            id: $leftId.'-'.$rightId,
            left: AdminProductReferenceItemProvider::toOutput($left),
            right: AdminProductReferenceItemProvider::toOutput($right),
            leftOfferCount: $this->deduplicationService->countMerchantOffers($left),
            rightOfferCount: $this->deduplicationService->countMerchantOffers($right),
            reason: $this->deduplicationService->matchReason($left, $right),
        );
    }
}
