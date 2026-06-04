<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductImage;
use App\Entity\ProductReference;
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
