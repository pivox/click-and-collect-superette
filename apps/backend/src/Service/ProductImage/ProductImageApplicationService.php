<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use App\Entity\ProductImage;
use App\Enum\ProductImageLicenseCode;
use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Single, reusable entry point of the product image pipeline.
 *
 * Deliberately decoupled from any controller so it can be called from:
 *   1. the admin upload endpoint (AdminProductReferenceImageController);
 *   2. the AI enrichment applier (ProductAiEnrichmentResultApplier) — see the
 *      docs/roadmap/product-images-web-mobile.md extension note;
 *   3. future catalogue-by-photo imports and merchant contributions.
 *
 * Governance rule enforced here: only an admin upload can yield a Verified
 * (official) image. AI / contribution / external sources are always stored as a
 * candidate or needs_review image and require explicit admin validation before
 * they could ever become the referential picture.
 */
final readonly class ProductImageApplicationService
{
    public function __construct(
        private ProductImageVariantGenerator $variantGenerator,
        private ProductImageStorage $storage,
        private ProductImageRepository $repository,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    public function store(ProductImageStoreCommand $command): ProductImage
    {
        $status = $this->resolveStatus($command);
        $licenseCode = $this->resolveLicenseCode($command);

        // PRODUCT-IMAGE-004 blocking rule, enforced at the single point where an
        // image can become Verified: unknown usage rights can never yield the
        // official picture. Checked before any file is written.
        if (ProductImageStatus::Verified === $status && !$licenseCode->allowsOfficialPublication()) {
            throw new ConflictHttpException('PRODUCT_IMAGE_LICENSE_UNKNOWN');
        }

        $generated = $this->variantGenerator->generate(
            $command->contents,
            $command->stripOriginalMetadata,
            $command->minDimension,
        );

        $image = (new ProductImage())
            ->setProductReference($command->productReference)
            ->setProductReferenceProposal($command->proposal)
            ->setMerchantLocalProduct($command->merchantLocalProduct)
            ->setSource($command->source)
            ->setStatus($status)
            ->setMimeType($generated->mimeType)
            ->setWidth($generated->width)
            ->setHeight($generated->height)
            ->setAltText($this->normalizeAltText($command->altText))
            ->setLicenseCode($licenseCode)
            ->setSourceName($this->normalizeShortText($command->sourceName))
            ->setSourceUrl($this->normalizeShortText($command->sourceUrl, 2048))
            ->setAttributionText($this->normalizeText($command->attributionText))
            ->setPermissionReference($this->normalizeShortText($command->permissionReference))
            ->setCapturedAt($command->capturedAt)
            ->setCollectedAt(new \DateTimeImmutable());

        $stored = $this->storage->store($image->getStorageKey(), $generated);
        $image->setOriginalPath($stored->originalPath);
        $image->setVariants($stored->variants);

        // When a verified image is attached to a product reference it becomes the
        // single official picture. PRODUCT-IMAGE-004: the previous official image
        // is no longer deleted — it is archived and points to its successor
        // (supersededBy) so the provenance registry keeps the replacement history.
        // Original files are kept on disk with the archived row.
        //
        // Known limit (accepted, admin-only / low risk — same stance as the slug
        // race documented in AI_CONTEXT.md): two admins uploading for the SAME
        // reference at the exact same time can both read no/old official image
        // before either flush, briefly leaving two `verified` rows; catalog/admin
        // reads then pick the most recent by updatedAt. A DB-level guard (partial
        // unique index on product_reference_id WHERE status = 'verified') plus a
        // transactional delete-before-insert is the recommended hardening before
        // high concurrency — see docs/roadmap/product-images-web-mobile.md.
        $replaced = null;
        if (ProductImageStatus::Verified === $status && null !== $command->productReference) {
            $replaced = $this->repository->findOfficialForProductReference($command->productReference);
        } elseif (null !== $command->merchantLocalProduct) {
            // PRODUCT-IMAGE-003: a local product carries a single current merchant
            // photo — replacing it archives the previous one with the supersession
            // trail (#584 mechanism), files kept on disk.
            $replaced = $this->repository->findCurrentForMerchantLocalProduct($command->merchantLocalProduct);
        }

        $this->entityManager->persist($image);
        $this->entityManager->flush();

        if (null !== $replaced && !$replaced->getId()->equals($image->getId())) {
            $replaced->setStatus(ProductImageStatus::Archived);
            $replaced->setSupersededBy($image);
            $this->entityManager->flush();
        }

        $this->logger->info('product_image.stored', [
            'product_image_id' => $image->getId()->toRfc4122(),
            'source' => $command->source->value,
            'status' => $status->value,
            'license_code' => $licenseCode->value,
            'product_reference_id' => $command->productReference?->getId()->toRfc4122(),
            'merchant_local_product_id' => $command->merchantLocalProduct?->getId()->toRfc4122(),
        ]);

        return $image;
    }

    /**
     * Permanently delete a stored image (DB row + files on disk).
     */
    public function delete(ProductImage $image): void
    {
        $storageKey = $image->getStorageKey();
        $this->entityManager->remove($image);
        $this->entityManager->flush();
        $this->storage->remove($storageKey);
    }

    private function resolveStatus(ProductImageStoreCommand $command): ProductImageStatus
    {
        $status = $command->statusOverride ?? $command->source->defaultStatus();

        // Hard guard: a non-admin source can never produce an official image,
        // even if the caller explicitly asked for it.
        if (ProductImageStatus::Verified === $status && !$command->source->canProduceVerifiedImage()) {
            $this->logger->warning('product_image.verified_status_denied_for_source', [
                'source' => $command->source->value,
            ]);

            return ProductImageStatus::NeedsReview;
        }

        return $status;
    }

    /**
     * Usage rights default: an admin upload is assumed to be an internal or
     * authorized shot (platform_owned — assumption documented in issue #584);
     * every other source stays unknown until an admin documents its provenance.
     */
    private function resolveLicenseCode(ProductImageStoreCommand $command): ProductImageLicenseCode
    {
        if (null !== $command->licenseCode) {
            return $command->licenseCode;
        }

        return ProductImageSource::AdminUpload === $command->source
            ? ProductImageLicenseCode::PlatformOwned
            : ProductImageLicenseCode::Unknown;
    }

    private function normalizeShortText(?string $value, int $maxLength = 255): ?string
    {
        $normalized = $this->normalizeText($value);

        return null === $normalized ? null : mb_substr($normalized, 0, $maxLength);
    }

    private function normalizeText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function normalizeAltText(?string $altText): ?string
    {
        if (null === $altText) {
            return null;
        }

        $trimmed = trim($altText);

        return '' === $trimmed ? null : mb_substr($trimmed, 0, 255);
    }

    /**
     * @return list<string>
     */
    public function allowedSources(): array
    {
        return ProductImageSource::values();
    }
}
