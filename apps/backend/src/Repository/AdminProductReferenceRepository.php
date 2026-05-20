<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class AdminProductReferenceRepository
{
    public function __construct(
        private ProductReferenceRepository $productReferenceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOne(string $id): ?ProductReference
    {
        /** @var ProductReference|null $ref */
        $ref = $this->productReferenceRepository->createQueryBuilder('pr')
            ->andWhere('pr.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $ref;
    }

    public function findOneByBarcode(string $barcode): ?ProductReference
    {
        return $this->productReferenceRepository->findOneBy(['barcode' => $barcode]);
    }

    /**
     * @return list<ProductReference>
     */
    public function findPaginated(
        int $limit,
        int $offset,
        ?string $q = null,
        ?string $categoryId = null,
        ?string $brandId = null,
        ?string $status = null,
    ): array {
        $qb = $this->productReferenceRepository->createQueryBuilder('pr')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c')
            ->orderBy('pr.nameFr', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->applyFilters($qb, $q, $categoryId, $brandId, $status);

        /* @var list<ProductReference> */
        return $qb->getQuery()->getResult();
    }

    public function countFiltered(
        ?string $q = null,
        ?string $categoryId = null,
        ?string $brandId = null,
        ?string $status = null,
    ): int {
        $qb = $this->productReferenceRepository->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c');

        $this->applyFilters($qb, $q, $categoryId, $brandId, $status);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyFilters(
        QueryBuilder $qb,
        ?string $q,
        ?string $categoryId,
        ?string $brandId,
        ?string $status,
    ): void {
        if (null !== $q && '' !== $q) {
            $qb->andWhere('(LOWER(pr.nameFr) LIKE LOWER(:q) OR (pr.nameAr IS NOT NULL AND LOWER(pr.nameAr) LIKE LOWER(:q)) OR pr.barcode = :barcode_exact)')
                ->setParameter('q', '%'.$q.'%')
                ->setParameter('barcode_exact', $q);
        }

        if (null !== $categoryId && '' !== $categoryId && Uuid::isValid($categoryId)) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId, 'uuid');
        }

        if (null !== $brandId && '' !== $brandId && Uuid::isValid($brandId)) {
            $qb->andWhere('b.id = :brandId')
                ->setParameter('brandId', $brandId, 'uuid');
        }

        if (null !== $status && '' !== $status) {
            $statusEnum = ProductReferenceStatus::tryFrom($status);
            if (null !== $statusEnum) {
                $qb->andWhere('pr.status = :status')
                    ->setParameter('status', $statusEnum);
            }
        }
    }

    public function save(ProductReference $productReference): void
    {
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();
    }
}
