<?php

declare(strict_types=1);

namespace App\Service\ProductImage;

use App\Entity\ProductImage;
use App\Enum\ProductImageSource;
use App\Enum\ProductImageStatus;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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

        $generated = $this->variantGenerator->generate($command->contents);

        $image = (new ProductImage())
            ->setProductReference($command->productReference)
            ->setProductReferenceProposal($command->proposal)
            ->setSource($command->source)
            ->setStatus($status)
            ->setMimeType($generated->mimeType)
            ->setWidth($generated->width)
            ->setHeight($generated->height)
            ->setAltText($this->normalizeAltText($command->altText));

        $stored = $this->storage->store($image->getStorageKey(), $generated);
        $image->setOriginalPath($stored->originalPath);
        $image->setVariants($stored->variants);

        // When a verified image is attached to a product reference it becomes the
        // When a verified image is attached to a product reference it becomes the
        // single official picture: clean up the previous one (DB row + files).
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
        }

        $this->entityManager->persist($image);
        $this->entityManager->flush();

        if (null !== $replaced && !$replaced->getId()->equals($image->getId())) {
            $previousStorageKey = $replaced->getStorageKey();
            $this->entityManager->remove($replaced);
            $this->entityManager->flush();
            $this->storage->remove($previousStorageKey);
        }

        $this->logger->info('product_image.stored', [
            'product_image_id' => $image->getId()->toRfc4122(),
            'source' => $command->source->value,
            'status' => $status->value,
            'product_reference_id' => $command->productReference?->getId()->toRfc4122(),
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
