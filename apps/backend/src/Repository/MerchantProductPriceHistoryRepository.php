<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantProduct;
use App\Entity\MerchantProductPriceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantProductPriceHistory>
 */
class MerchantProductPriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantProductPriceHistory::class);
    }

    /**
     * @return list<MerchantProductPriceHistory>
     */
    public function findForMerchantProduct(MerchantProduct $merchantProduct): array
    {
        /** @var list<MerchantProductPriceHistory> $items */
        $items = $this->findBy(
            ['merchantProduct' => $merchantProduct],
            ['changedAt' => 'DESC', 'createdAt' => 'DESC'],
        );

        return $items;
    }
}
