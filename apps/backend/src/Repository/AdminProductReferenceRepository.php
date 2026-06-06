<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductImage;
use App\Entity\ProductReference;
use App\Enum\ProductImageStatus;
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
        /** @var ProductReference|null $ref */
        $ref = $this->productReferenceRepository->createQueryBuilder('pr')
            ->andWhere('pr.barcode = :barcode')
            ->andWhere('pr.status != :archived')
            ->setParameter('barcode', trim($barcode))
            ->setParameter('archived', ProductReferenceStatus::Archived)
            ->orderBy('pr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $ref;
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
        ?int $qualityMin = null,
        ?int $qualityMax = null,
        ?string $sort = null,
        string $direction = 'asc',
    ): array {
        $qb = $this->productReferenceRepository->createQueryBuilder('pr')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c');

        $this->applyFilters($qb, $q, $categoryId, $brandId, $status);
        $usesQualityScore = null !== $qualityMin || null !== $qualityMax || 'quality_score' === $sort;
        if ($usesQualityScore) {
            $qb->setParameter('verifiedImageStatus', ProductImageStatus::Verified)
                ->setParameter('approvedReferenceStatus', ProductReferenceStatus::Approved);
            $this->applyQualityFilters($qb, $qualityMin, $qualityMax);
        }

        if ('quality_score' === $sort) {
            $qb->addSelect($this->qualityScoreExpression('piQualitySort').' AS HIDDEN qualityScore');
            $qb->orderBy('qualityScore', $direction);
        } else {
            $qb->orderBy('pr.nameFr', 'ASC');
        }

        $qb->addOrderBy('pr.nameFr', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /* @var list<ProductReference> */
        return $qb->getQuery()->getResult();
    }

    public function countFiltered(
        ?string $q = null,
        ?string $categoryId = null,
        ?string $brandId = null,
        ?string $status = null,
        ?int $qualityMin = null,
        ?int $qualityMax = null,
    ): int {
        $qb = $this->productReferenceRepository->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->join('pr.brand', 'b')
            ->join('pr.category', 'c');

        $this->applyFilters($qb, $q, $categoryId, $brandId, $status);
        if (null !== $qualityMin || null !== $qualityMax) {
            $qb->setParameter('verifiedImageStatus', ProductImageStatus::Verified)
                ->setParameter('approvedReferenceStatus', ProductReferenceStatus::Approved);
            $this->applyQualityFilters($qb, $qualityMin, $qualityMax);
        }

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

    private function qualityScoreExpression(string $imageAlias): string
    {
        $imageClass = ProductImage::class;

        return "(CASE WHEN TRIM(pr.nameFr) <> '' THEN 20 ELSE 0 END"
            ." + CASE WHEN TRIM(b.canonicalName) <> '' THEN 10 ELSE 0 END"
            ." + CASE WHEN TRIM(c.nameFr) <> '' THEN 10 ELSE 0 END"
            .' + CASE WHEN pr.volume IS NOT NULL THEN 10 ELSE 0 END'
            .' + 10'
            ." + CASE WHEN pr.barcode IS NOT NULL AND TRIM(pr.barcode) <> '' THEN 15 ELSE 0 END"
            ." + CASE WHEN EXISTS (SELECT 1 FROM {$imageClass} {$imageAlias}"
            ." WHERE {$imageAlias}.productReference = pr"
            ." AND {$imageAlias}.status = :verifiedImageStatus) THEN 15 ELSE 0 END"
            .' + CASE WHEN pr.status = :approvedReferenceStatus THEN 10 ELSE 0 END)';
    }

    private function applyQualityFilters(
        QueryBuilder $qb,
        ?int $qualityMin,
        ?int $qualityMax,
    ): void {
        if (null !== $qualityMin) {
            $qb->andWhere($this->qualityScoreExpression('piQualityMin').' >= :qualityMin')
                ->setParameter('qualityMin', $qualityMin);
        }

        if (null !== $qualityMax) {
            $qb->andWhere($this->qualityScoreExpression('piQualityMax').' <= :qualityMax')
                ->setParameter('qualityMax', $qualityMax);
        }
    }

    public function save(ProductReference $productReference): void
    {
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();
    }
}
