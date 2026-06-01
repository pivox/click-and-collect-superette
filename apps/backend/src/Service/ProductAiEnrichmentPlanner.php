<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductAiEnrichmentJobStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductAiEnrichmentPlanner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function planMissingProductJobs(int $limit): ProductAiEnrichmentPlanResult
    {
        $limit = max(1, $limit);
        $products = $this->findProductsNeedingEnrichment($limit);
        $created = 0;

        foreach ($products as $productReference) {
            $this->entityManager->persist(new ProductAiEnrichmentJob($productReference));
            ++$created;
        }

        $this->entityManager->flush();

        return new ProductAiEnrichmentPlanResult(\count($products), $created);
    }

    /** @return list<ProductReference> */
    private function findProductsNeedingEnrichment(int $limit): array
    {
        /** @var list<ProductReference> $products */
        $products = $this->entityManager->createQuery(
            'SELECT pr FROM App\Entity\ProductReference pr
             JOIN pr.brand b
             LEFT JOIN App\Entity\ProductAiEnrichmentJob j WITH j.productReference = pr AND j.status IN (:openStatuses)
             WHERE pr.aiEnrichedAt IS NULL
             AND (
                pr.barcode IS NULL
                OR pr.nameAr IS NULL
                OR b.slug = :genericBrand
             )
             AND j.id IS NULL
             ORDER BY pr.createdAt ASC'
        )
            ->setParameter('genericBrand', 'marque-non-verifiee')
            ->setParameter('openStatuses', [
                ProductAiEnrichmentJobStatus::Pending,
                ProductAiEnrichmentJobStatus::Submitted,
                ProductAiEnrichmentJobStatus::Succeeded,
            ])
            ->setMaxResults($limit)
            ->getResult();

        return $products;
    }
}
