<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantLocalProduct;
use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Enum\ProductImageLicenseCode;
use App\Enum\ProductImageStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImage>
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    public function save(ProductImage $image): void
    {
        $em = $this->getEntityManager();
        $em->persist($image);
        $em->flush();
    }

    public function remove(ProductImage $image): void
    {
        $em = $this->getEntityManager();
        $em->remove($image);
        $em->flush();
    }

    /**
     * The official (verified) image of a product reference, most recent first.
     * findOneBy is used instead of a DQL parameter to stay reliable on the
     * SQLite test backend where UUID columns are stored as BLOB (backend-pattern #2).
     */
    public function findOfficialForProductReference(ProductReference $productReference): ?ProductImage
    {
        return $this->findOneBy(
            ['productReference' => $productReference, 'status' => ProductImageStatus::Verified],
            ['updatedAt' => 'DESC'],
        );
    }

    /**
     * The current merchant photo of a local product (PRODUCT-IMAGE-003): the
     * still-candidate contribution, the archived ones being its history.
     * Criteria API keeps UUID matching reliable on SQLite (backend-pattern #2).
     */
    public function findCurrentForMerchantLocalProduct(MerchantLocalProduct $localProduct): ?ProductImage
    {
        return $this->findOneBy(
            ['merchantLocalProduct' => $localProduct, 'status' => ProductImageStatus::Candidate],
            ['updatedAt' => 'DESC'],
        );
    }

    /**
     * Batch-load the current merchant photos for a set of local products, keyed
     * by the local product RFC-4122 id. Avoids N+1 queries on catalog pages.
     *
     * @param list<MerchantLocalProduct> $localProducts
     *
     * @return array<string, ProductImage> map merchantLocalProductId => current photo
     */
    public function findCurrentByMerchantLocalProducts(array $localProducts): array
    {
        if ([] === $localProducts) {
            return [];
        }

        /** @var list<ProductImage> $images */
        $images = $this->findBy([
            'merchantLocalProduct' => $localProducts,
            'status' => ProductImageStatus::Candidate,
        ], ['updatedAt' => 'ASC']);

        $map = [];
        foreach ($images as $image) {
            $localProduct = $image->getMerchantLocalProduct();
            if (null === $localProduct) {
                continue;
            }
            // Later (ASC) candidates overwrite earlier ones → keep the most recent.
            $map[$localProduct->getId()->toRfc4122()] = $image;
        }

        return $map;
    }

    /**
     * Paginated provenance-registry listing (PRODUCT-IMAGE-004). Criteria API is
     * used instead of DQL parameters to stay reliable on the SQLite test backend
     * (backend-pattern #2); newest images first.
     *
     * @return list<ProductImage>
     */
    public function findPaginatedForAdmin(
        ?ProductImageLicenseCode $license,
        ?ProductImageStatus $status,
        int $limit,
        int $offset,
    ): array {
        return $this->findBy(
            $this->adminCriteria($license, $status),
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        );
    }

    public function countForAdmin(?ProductImageLicenseCode $license, ?ProductImageStatus $status): int
    {
        return $this->count($this->adminCriteria($license, $status));
    }

    /**
     * @return array<string, ProductImageLicenseCode|ProductImageStatus>
     */
    private function adminCriteria(?ProductImageLicenseCode $license, ?ProductImageStatus $status): array
    {
        $criteria = [];
        if (null !== $license) {
            $criteria['licenseCode'] = $license;
        }
        if (null !== $status) {
            $criteria['status'] = $status;
        }

        return $criteria;
    }

    /**
     * Batch-load the official images for a set of product references, keyed by the
     * product reference RFC-4122 id. Avoids N+1 queries when building a catalog page.
     *
     * @param list<ProductReference> $productReferences
     *
     * @return array<string, ProductImage> map productReferenceId => official image
     */
    public function findOfficialByProductReferences(array $productReferences): array
    {
        if ([] === $productReferences) {
            return [];
        }

        /** @var list<ProductImage> $images */
        $images = $this->findBy([
            'productReference' => $productReferences,
            'status' => ProductImageStatus::Verified,
        ], ['updatedAt' => 'ASC']);

        $map = [];
        foreach ($images as $image) {
            $reference = $image->getProductReference();
            if (null === $reference) {
                continue;
            }
            // Later (ASC) verified images overwrite earlier ones → keep the most recent.
            $map[$reference->getId()->toRfc4122()] = $image;
        }

        return $map;
    }
}
