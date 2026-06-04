<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductReference>
 */
class ProductReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReference::class);
    }

    /**
     * @return list<ProductReference>
     */
    public function search(
        ?string $query = null,
        ?string $brandId = null,
        ?string $categorySlug = null,
        int $limit = 20,
        int $offset = 0,
        ?string $barcode = null,
    ): array {
        $qb = $this->createQueryBuilder('pr')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c')
            ->where('pr.status = :status')
            ->setParameter('status', ProductReferenceStatus::Approved)
            ->orderBy('pr.nameFr', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $query) {
            $qb->andWhere('(LOWER(pr.nameFr) LIKE LOWER(:q) OR LOWER(b.canonicalName) LIKE LOWER(:q) OR pr.barcode = :exact)')
                ->setParameter('q', '%'.$query.'%')
                ->setParameter('exact', $query);
        }

        if (null !== $barcode) {
            $qb->andWhere('pr.barcode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        if (null !== $brandId) {
            $qb->andWhere('b.id = :brandId')
                ->setParameter('brandId', $brandId);
        }

        if (null !== $categorySlug) {
            $qb->andWhere('c.slug = :categorySlug')
                ->setParameter('categorySlug', $categorySlug);
        }

        /* @var list<ProductReference> */
        return $qb->getQuery()->getResult();
    }

    public function countSearch(
        ?string $query = null,
        ?string $brandId = null,
        ?string $categorySlug = null,
        ?string $barcode = null,
    ): int {
        $qb = $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c')
            ->where('pr.status = :status')
            ->setParameter('status', ProductReferenceStatus::Approved);

        if (null !== $query) {
            $qb->andWhere('(LOWER(pr.nameFr) LIKE LOWER(:q) OR LOWER(b.canonicalName) LIKE LOWER(:q) OR pr.barcode = :exact)')
                ->setParameter('q', '%'.$query.'%')
                ->setParameter('exact', $query);
        }

        if (null !== $barcode) {
            $qb->andWhere('pr.barcode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        if (null !== $brandId) {
            $qb->andWhere('b.id = :brandId')
                ->setParameter('brandId', $brandId);
        }

        if (null !== $categorySlug) {
            $qb->andWhere('c.slug = :categorySlug')
                ->setParameter('categorySlug', $categorySlug);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findOneApprovedByBarcode(string $barcode): ?ProductReference
    {
        return $this->createQueryBuilder('pr')
            ->where('pr.status = :status')
            ->andWhere('pr.barcode = :barcode')
            ->setParameter('status', ProductReferenceStatus::Approved)
            ->setParameter('barcode', trim($barcode))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneApprovedByIdentity(
        string $brandName,
        string $nameFr,
        string $volume,
        ProductUnit $unit,
    ): ?ProductReference {
        return $this->createQueryBuilder('pr')
            ->join('pr.brand', 'b')
            ->where('pr.status = :status')
            ->andWhere('LOWER(b.canonicalName) = LOWER(:brandName)')
            ->andWhere('LOWER(pr.nameFr) = LOWER(:nameFr)')
            ->andWhere('pr.volume = :volume')
            ->andWhere('pr.unit = :unit')
            ->setParameter('status', ProductReferenceStatus::Approved)
            ->setParameter('brandName', trim($brandName))
            ->setParameter('nameFr', trim($nameFr))
            ->setParameter('volume', $volume)
            ->setParameter('unit', $unit)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
