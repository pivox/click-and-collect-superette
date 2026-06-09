<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\ApiResource\ProductImageOutput;
use App\Entity\ProductReference;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductImageRepository;
use App\Service\ProductImage\ProductImageUrlBuilder;
use App\Service\ProductReferenceQualityScorer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminProductReferenceOutput>
 */
final readonly class AdminProductReferenceItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
        private ProductImageRepository $productImageRepository,
        private ProductImageUrlBuilder $productImageUrlBuilder,
        private ProductReferenceQualityScorer $qualityScorer,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceOutput
    {
        $productReferenceId = (string) ($uriVariables['productReferenceId'] ?? '');
        if (!Uuid::isValid($productReferenceId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $productReference = $this->adminProductReferenceRepository->findOne($productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $image = $this->productImageUrlBuilder->build(
            $this->productImageRepository->findOfficialForProductReference($productReference),
        );

        return self::toOutput($productReference, $image, $this->qualityScorer);
    }

    public static function toOutput(
        ProductReference $productReference,
        ?ProductImageOutput $image = null,
        ?ProductReferenceQualityScorer $qualityScorer = null,
    ): AdminProductReferenceOutput {
        $qualityScorer ??= new ProductReferenceQualityScorer();
        $qualityScore = $qualityScorer->score($productReference, null !== $image);

        return new AdminProductReferenceOutput(
            id: $productReference->getId()->toRfc4122(),
            nameFr: $productReference->getNameFr(),
            nameAr: $productReference->getNameAr(),
            variantFr: $productReference->getVariantFr(),
            variantAr: $productReference->getVariantAr(),
            brandId: $productReference->getBrand()->getId()->toRfc4122(),
            brandName: $productReference->getBrand()->getCanonicalName(),
            categoryId: $productReference->getCategory()->getId()->toRfc4122(),
            categoryNameFr: $productReference->getCategory()->getNameFr(),
            categoryNameAr: $productReference->getCategory()->getNameAr(),
            unit: $productReference->getUnit()->value,
            volume: $productReference->getVolume(),
            barcode: $productReference->getBarcode(),
            aliases: $productReference->getAliases(),
            country: $productReference->getCountry(),
            status: $productReference->getStatus()->value,
            rejectionReason: $productReference->getRejectionReason(),
            createdAt: $productReference->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $productReference->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            qualityScore: $qualityScore,
            qualityLevel: $qualityScorer->level($qualityScore),
            image: $image,
        );
    }
}
