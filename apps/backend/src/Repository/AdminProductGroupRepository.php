<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Enum\ProductGroupStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class AdminProductGroupRepository
{
    public function __construct(
        private ProductGroupRepository $productGroupRepository,
        private ProductGroupItemRepository $productGroupItemRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOne(string $id): ?ProductGroup
    {
        /** @var ProductGroup|null $group */
        $group = $this->productGroupRepository->createQueryBuilder('pg')
            ->leftJoin('pg.items', 'pgi')
            ->addSelect('pgi')
            ->leftJoin('pgi.productReference', 'pr')
            ->addSelect('pr')
            ->leftJoin('pr.brand', 'b')
            ->addSelect('b')
            ->leftJoin('pr.category', 'c')
            ->addSelect('c')
            ->andWhere('pg.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $group;
    }

    public function findOneBySlug(string $slug): ?ProductGroup
    {
        return $this->productGroupRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<ProductGroup>
     */
    public function findPaginated(
        int $limit,
        int $offset,
        ?string $q = null,
        ?string $status = null,
        ?string $marketCountry = null,
    ): array {
        $qb = $this->productGroupRepository->createQueryBuilder('pg')
            ->leftJoin('pg.items', 'itemsCount')
            ->addSelect('COUNT(itemsCount.id) AS HIDDEN itemCount')
            ->groupBy('pg.id');

        $this->applyFilters($qb, $q, $status, $marketCountry);

        $qb->orderBy('pg.sortOrder', 'ASC')
            ->addOrderBy('pg.nameFr', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /* @var list<ProductGroup> */
        return $qb->getQuery()->getResult();
    }

    public function countFiltered(?string $q = null, ?string $status = null, ?string $marketCountry = null): int
    {
        $qb = $this->productGroupRepository->createQueryBuilder('pg')
            ->select('COUNT(pg.id)');

        $this->applyFilters($qb, $q, $status, $marketCountry);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findItem(ProductGroup $group, string $itemId): ?ProductGroupItem
    {
        /** @var ProductGroupItem|null $item */
        $item = $this->productGroupItemRepository->createQueryBuilder('pgi')
            ->join('pgi.productGroup', 'pg')
            ->join('pgi.productReference', 'pr')
            ->addSelect('pr')
            ->join('pr.brand', 'b')
            ->addSelect('b')
            ->join('pr.category', 'c')
            ->addSelect('c')
            ->andWhere('pg.id = :groupId')
            ->andWhere('pgi.id = :itemId')
            ->setParameter('groupId', $group->getId(), 'uuid')
            ->setParameter('itemId', $itemId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $item;
    }

    public function findItemByProductReference(ProductGroup $group, ProductReference $productReference): ?ProductGroupItem
    {
        /** @var ProductGroupItem|null $item */
        $item = $this->productGroupItemRepository->findOneBy([
            'productGroup' => $group,
            'productReference' => $productReference,
        ]);

        return $item;
    }

    public function save(ProductGroup|ProductGroupItem $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function remove(ProductGroupItem $item): void
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    private function applyFilters(QueryBuilder $qb, ?string $q, ?string $status, ?string $marketCountry): void
    {
        if (null !== $q && '' !== $q) {
            $qb->andWhere('(LOWER(pg.nameFr) LIKE LOWER(:q) OR (pg.nameAr IS NOT NULL AND LOWER(pg.nameAr) LIKE LOWER(:q)) OR LOWER(pg.slug) LIKE LOWER(:q) OR (pg.descriptionFr IS NOT NULL AND LOWER(pg.descriptionFr) LIKE LOWER(:q)))')
                ->setParameter('q', '%'.$q.'%');
        }

        if (null !== $status && '' !== $status) {
            $qb->andWhere('pg.status = :status')
                ->setParameter('status', ProductGroupStatus::from($status));
        }

        if (null !== $marketCountry && '' !== $marketCountry) {
            $qb->andWhere('pg.marketCountry = :marketCountry')
                ->setParameter('marketCountry', strtoupper($marketCountry));
        }
    }
}
