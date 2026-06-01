<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductAiEnrichmentJobStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductAiEnrichmentJob>
 */
class ProductAiEnrichmentJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductAiEnrichmentJob::class);
    }

    public function hasOpenJobForProduct(ProductReference $productReference): bool
    {
        return null !== $this->findOneBy([
            'productReference' => $productReference,
            'status' => [
                ProductAiEnrichmentJobStatus::Pending,
                ProductAiEnrichmentJobStatus::Submitted,
                ProductAiEnrichmentJobStatus::Succeeded,
            ],
        ]);
    }

    /** @return list<ProductAiEnrichmentJob> */
    public function findPending(int $limit): array
    {
        /* @var list<ProductAiEnrichmentJob> */
        return $this->findBy(['status' => ProductAiEnrichmentJobStatus::Pending], ['createdAt' => 'ASC'], $limit);
    }

    /** @return list<ProductAiEnrichmentJob> */
    public function findSubmitted(): array
    {
        /* @var list<ProductAiEnrichmentJob> */
        return $this->findBy(['status' => ProductAiEnrichmentJobStatus::Submitted], ['submittedAt' => 'ASC']);
    }
}
