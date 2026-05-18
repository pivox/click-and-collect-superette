<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminCategoryRepository
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOne(string $id): ?Category
    {
        /** @var Category|null $category */
        $category = $this->categoryRepository->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $category;
    }

    public function findOneBySlug(string $slug): ?Category
    {
        return $this->categoryRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Category>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /** @var list<Category> $categories */
        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.nameFr', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $categories;
    }

    public function countAll(): int
    {
        return (int) $this->categoryRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLinkedProductReferences(Category $category): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pr.id)')
            ->from('App\Entity\ProductReference', 'pr')
            ->andWhere('pr.category = :category')
            ->setParameter('category', $category->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Category $category): void
    {
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    public function remove(Category $category): void
    {
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
