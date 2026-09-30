<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ProductImageProvenanceOutput;
use App\Dto\AdminProductImagePromoteInput;
use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Entity\User;
use App\Enum\ProductImageStatus;
use App\Provider\AdminProductImageCollectionProvider;
use App\Repository\ProductImageRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * PATCH /api/admin/product-images/{productImageId}/promote (PRODUCT-IMAGE-003).
 *
 * Promotes a merchant local-product photo to the shared referential picture.
 * Lead decision — the promotion is a LOGICAL DUPLICATION: a new ProductImage row
 * is created for the ProductReference, pointing to the same stored files, with
 * source merchant_contribution, status verified, the inherited license and the
 * copied provenance (sourceName kept = shop name). The original row stays the
 * shop's own photo, untouched. The previous official image of the reference, if
 * any, is archived with the supersession trail (#584 mechanism).
 *
 * Guards:
 * - only a non-archived image attached to a merchantLocalProduct is promotable
 *   (409 PRODUCT_IMAGE_NOT_PROMOTABLE);
 * - the license must allow official publication (409 PRODUCT_IMAGE_LICENSE_UNKNOWN).
 *
 * @implements ProcessorInterface<AdminProductImagePromoteInput, ProductImageProvenanceOutput>
 */
final readonly class AdminPromoteProductImageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductImageRepository $productImageRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductImageProvenanceOutput
    {
        if (!$data instanceof AdminProductImagePromoteInput) {
            throw new \InvalidArgumentException('AdminProductImagePromoteInput expected.');
        }

        $productImageId = (string) ($uriVariables['productImageId'] ?? '');
        if (!Uuid::isValid($productImageId)) {
            throw new NotFoundHttpException('PRODUCT_IMAGE_NOT_FOUND');
        }

        $sourceImage = $this->productImageRepository->find(Uuid::fromString($productImageId));
        if (!$sourceImage instanceof ProductImage) {
            throw new NotFoundHttpException('PRODUCT_IMAGE_NOT_FOUND');
        }

        $localProduct = $sourceImage->getMerchantLocalProduct();
        if (null === $localProduct || ProductImageStatus::Archived === $sourceImage->getStatus()) {
            throw new ConflictHttpException('PRODUCT_IMAGE_NOT_PROMOTABLE');
        }

        // PRODUCT-IMAGE-004 blocking rule: undocumented rights never become official.
        if (!$sourceImage->getLicenseCode()->allowsOfficialPublication()) {
            throw new ConflictHttpException('PRODUCT_IMAGE_LICENSE_UNKNOWN');
        }

        // Assert\NotBlank + Assert\Uuid already validated the value (pattern #14).
        $productReference = $this->entityManager->find(
            ProductReference::class,
            Uuid::fromString((string) $data->productReferenceId),
        );
        if (!$productReference instanceof ProductReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $admin = $this->security->getUser();

        // Logical duplication: same stored files/paths, new row owned by the reference.
        $promoted = (new ProductImage())
            ->setProductReference($productReference)
            ->setOriginalPath($sourceImage->getOriginalPath())
            ->setMimeType($sourceImage->getMimeType())
            ->setWidth($sourceImage->getWidth())
            ->setHeight($sourceImage->getHeight())
            ->setVariants($sourceImage->getVariants())
            ->setSource($sourceImage->getSource())
            ->setStatus(ProductImageStatus::Verified)
            ->setAltText($sourceImage->getAltText())
            ->setLicenseCode($sourceImage->getLicenseCode())
            ->setSourceName($sourceImage->getSourceName())
            ->setSourceUrl($sourceImage->getSourceUrl())
            ->setAttributionText($sourceImage->getAttributionText())
            ->setPermissionReference($sourceImage->getPermissionReference())
            ->setCapturedAt($sourceImage->getCapturedAt())
            ->setCollectedAt($sourceImage->getCollectedAt())
            ->setApprovedAt(new \DateTimeImmutable())
            ->setApprovedBy($admin instanceof User ? $admin : null);

        // #584 mechanism: the replaced official picture is archived, never deleted.
        $previousOfficial = $this->productImageRepository->findOfficialForProductReference($productReference);
        if (null !== $previousOfficial) {
            $previousOfficial->setStatus(ProductImageStatus::Archived);
            $previousOfficial->setSupersededBy($promoted);
        }

        $this->entityManager->persist($promoted);

        $this->auditLogger->log(
            action: 'product_image.promoted_from_merchant',
            resourceType: 'product_image',
            resourceId: $promoted->getId()->toRfc4122(),
            summary: \sprintf(
                'Photo marchand promue comme image officielle de "%s".',
                $productReference->getNameFr(),
            ),
            metadata: [
                'source_product_image_id' => $sourceImage->getId()->toRfc4122(),
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'merchant_local_product_id' => $localProduct->getId()->toRfc4122(),
                'shop_id' => $localProduct->getShop()->getId()->toRfc4122(),
                'license_code' => $promoted->getLicenseCode()->value,
                'superseded_product_image_id' => $previousOfficial?->getId()->toRfc4122(),
            ],
        );

        $this->entityManager->flush();

        return AdminProductImageCollectionProvider::toOutput($promoted);
    }
}
