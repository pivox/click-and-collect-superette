<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductImageListOutput;
use App\ApiResource\ProductImageProvenanceOutput;
use App\Entity\ProductImage;
use App\Enum\ProductImageLicenseCode;
use App\Enum\ProductImageStatus;
use App\Repository\ProductImageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * GET /api/admin/product-images — provenance registry listing (PRODUCT-IMAGE-004).
 *
 * Filters (license, status) are validated here, not in the QueryParameter schema
 * (patterns #5 and #12): an invalid value yields a 400 with an explicit code.
 *
 * @implements ProviderInterface<AdminProductImageListOutput>
 */
final readonly class AdminProductImageCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private ProductImageRepository $productImageRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductImageListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_PRODUCT_IMAGE_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_PRODUCT_IMAGE_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $license = null;
        $rawLicense = $request?->query->get('license');
        if (null !== $rawLicense && '' !== $rawLicense) {
            $license = ProductImageLicenseCode::tryFrom((string) $rawLicense);
            if (null === $license) {
                throw new BadRequestHttpException('ADMIN_PRODUCT_IMAGE_INVALID_LICENSE_FILTER');
            }
        }

        $status = null;
        $rawStatus = $request?->query->get('status');
        if (null !== $rawStatus && '' !== $rawStatus) {
            $status = ProductImageStatus::tryFrom((string) $rawStatus);
            if (null === $status) {
                throw new BadRequestHttpException('ADMIN_PRODUCT_IMAGE_INVALID_STATUS_FILTER');
            }
        }

        $images = $this->productImageRepository->findPaginatedForAdmin($license, $status, $limit, $offset);

        return new AdminProductImageListOutput(
            id: 'admin-product-images',
            items: array_map(static fn (ProductImage $image): ProductImageProvenanceOutput => self::toOutput($image), $images),
            page: $page,
            limit: $limit,
            total: $this->productImageRepository->countForAdmin($license, $status),
        );
    }

    public static function toOutput(ProductImage $image): ProductImageProvenanceOutput
    {
        return new ProductImageProvenanceOutput(
            id: $image->getId()->toRfc4122(),
            productReferenceId: $image->getProductReference()?->getId()->toRfc4122(),
            licenseCode: $image->getLicenseCode()->value,
            source: $image->getSource()->value,
            sourceName: $image->getSourceName(),
            sourceUrl: $image->getSourceUrl(),
            attributionText: $image->getAttributionText(),
            permissionReference: $image->getPermissionReference(),
            capturedAt: $image->getCapturedAt()?->format(\DateTimeInterface::ATOM),
            collectedAt: $image->getCollectedAt()?->format(\DateTimeInterface::ATOM),
            approvedAt: $image->getApprovedAt()?->format(\DateTimeInterface::ATOM),
            approvedByEmail: $image->getApprovedBy()?->getEmail(),
            supersededById: $image->getSupersededBy()?->getId()->toRfc4122(),
            status: $image->getStatus()->value,
            merchantLocalProductId: $image->getMerchantLocalProduct()?->getId()->toRfc4122(),
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
