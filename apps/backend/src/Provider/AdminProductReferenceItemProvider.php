<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\Entity\ProductReference;
use App\Repository\AdminProductReferenceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminProductReferenceOutput>
 */
final readonly class AdminProductReferenceItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
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

        return self::toOutput($productReference);
    }

    public static function toOutput(ProductReference $productReference): AdminProductReferenceOutput
    {
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
            createdAt: $productReference->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $productReference->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
