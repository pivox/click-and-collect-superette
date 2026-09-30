<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ProductImageProvenanceOutput;
use App\Dto\AdminProductImageProvenanceInput;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Enum\ProductImageLicenseCode;
use App\Provider\AdminProductImageCollectionProvider;
use App\Repository\ProductImageRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * PATCH /api/admin/product-images/{productImageId}/provenance (PRODUCT-IMAGE-004).
 *
 * Presence-based partial update: only the keys present in the JSON payload are
 * applied (an explicit null clears a nullable field). When the license moves out
 * of Unknown to a publishable value, the approval trail (approvedAt/approvedBy)
 * is recorded. Every effective change is audited with a before/after snapshot.
 *
 * @implements ProcessorInterface<AdminProductImageProvenanceInput, ProductImageProvenanceOutput>
 */
final readonly class AdminUpdateProductImageProvenanceProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductImageRepository $productImageRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
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
        if (!$data instanceof AdminProductImageProvenanceInput) {
            throw new \InvalidArgumentException('AdminProductImageProvenanceInput expected.');
        }

        $productImageId = (string) ($uriVariables['productImageId'] ?? '');
        if (!Uuid::isValid($productImageId)) {
            throw new NotFoundHttpException('PRODUCT_IMAGE_NOT_FOUND');
        }

        $image = $this->productImageRepository->find(Uuid::fromString($productImageId));
        if (!$image instanceof ProductImage) {
            throw new NotFoundHttpException('PRODUCT_IMAGE_NOT_FOUND');
        }

        $payload = $this->currentPayload();
        $before = [];
        $after = [];

        if (\array_key_exists('license_code', $payload) && null !== $data->licenseCode) {
            // Assert\Choice already guarantees a valid value (pattern #14).
            $newLicense = ProductImageLicenseCode::from($data->licenseCode);
            $previousLicense = $image->getLicenseCode();

            if ($newLicense !== $previousLicense) {
                // A verified (official) image can never fall back to unknown rights.
                if (!$newLicense->allowsOfficialPublication() && $image->getStatus()->isPubliclyExposable()) {
                    throw new ConflictHttpException('PRODUCT_IMAGE_LICENSE_UNKNOWN');
                }

                $before['license_code'] = $previousLicense->value;
                $after['license_code'] = $newLicense->value;
                $image->setLicenseCode($newLicense);

                // Approval trail: the license moves out of Unknown to a publishable value.
                if (ProductImageLicenseCode::Unknown === $previousLicense && $newLicense->allowsOfficialPublication()) {
                    $admin = $this->security->getUser();
                    $image->setApprovedAt(new \DateTimeImmutable());
                    $image->setApprovedBy($admin instanceof User ? $admin : null);
                    $after['approved_at'] = $image->getApprovedAt()?->format(\DateTimeInterface::ATOM);
                    $after['approved_by'] = $image->getApprovedBy()?->getEmail();
                }
            }
        }

        $this->applyText($payload, 'source_name', $data->sourceName, $image->getSourceName(), $image->setSourceName(...), $before, $after);
        $this->applyText($payload, 'source_url', $data->sourceUrl, $image->getSourceUrl(), $image->setSourceUrl(...), $before, $after);
        $this->applyText($payload, 'attribution_text', $data->attributionText, $image->getAttributionText(), $image->setAttributionText(...), $before, $after);
        $this->applyText($payload, 'permission_reference', $data->permissionReference, $image->getPermissionReference(), $image->setPermissionReference(...), $before, $after);

        if (\array_key_exists('captured_at', $payload)) {
            $newCapturedAt = $this->parseCapturedAt($data->capturedAt);
            if ($newCapturedAt?->format(\DateTimeInterface::ATOM) !== $image->getCapturedAt()?->format(\DateTimeInterface::ATOM)) {
                $before['captured_at'] = $image->getCapturedAt()?->format(\DateTimeInterface::ATOM);
                $after['captured_at'] = $newCapturedAt?->format(\DateTimeInterface::ATOM);
                $image->setCapturedAt($newCapturedAt);
            }
        }

        if ([] !== $after) {
            $this->auditLogger->log(
                action: 'product_image.provenance_updated',
                resourceType: 'product_image',
                resourceId: $image->getId()->toRfc4122(),
                summary: \sprintf('Provenance mise à jour pour l\'image produit %s.', $image->getId()->toRfc4122()),
                metadata: ['before' => $before, 'after' => $after],
            );
            $this->entityManager->flush();
        }

        return AdminProductImageCollectionProvider::toOutput($image);
    }

    /**
     * @param array<string, mixed>       $payload
     * @param callable(?string): mixed   $setter
     * @param array<string, string|null> $before
     * @param array<string, string|null> $after
     */
    private function applyText(array $payload, string $key, ?string $newValue, ?string $currentValue, callable $setter, array &$before, array &$after): void
    {
        if (!\array_key_exists($key, $payload)) {
            return;
        }

        $normalized = null !== $newValue ? trim($newValue) : null;
        $normalized = '' === $normalized ? null : $normalized;

        if ($normalized === $currentValue) {
            return;
        }

        $before[$key] = $currentValue;
        $after[$key] = $normalized;
        $setter($normalized);
    }

    private function parseCapturedAt(?string $raw): ?\DateTimeImmutable
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('PRODUCT_IMAGE_INVALID_CAPTURED_AT');
        }
    }

    /**
     * Raw JSON body keys — needed for presence-based partial updates (pattern
     * shared with AdminUpdateBrandProcessor).
     *
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
