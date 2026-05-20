<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Brand;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminBrandRepository
{
    public function __construct(
        private BrandRepository $brandRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOne(string $id): ?Brand
    {
        /** @var Brand|null $brand */
        $brand = $this->brandRepository->createQueryBuilder('b')
            ->andWhere('b.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $brand;
    }

    public function findOneBySlug(string $slug): ?Brand
    {
        return $this->brandRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Brand>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /** @var list<Brand> $brands */
        $brands = $this->brandRepository->createQueryBuilder('b')
            ->orderBy('b.canonicalName', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $brands;
    }

    public function countAll(): int
    {
        return (int) $this->brandRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasLinkedEntities(Brand $brand): bool
    {
        $id = $brand->getId();

        $refCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pr.id)')
            ->from('App\Entity\ProductReference', 'pr')
            ->andWhere('pr.brand = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        if ($refCount > 0) {
            return true;
        }

        $proposalCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\ProductReferenceProposal', 'p')
            ->andWhere('p.brand = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return $proposalCount > 0;
    }

    public function save(Brand $brand): void
    {
        $this->entityManager->persist($brand);
        $this->entityManager->flush();
    }

    public function remove(Brand $brand): void
    {
        $this->entityManager->remove($brand);
        $this->entityManager->flush();
    }
}
