<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\ShopOrderingPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopOrderingPolicy>
 */
class ShopOrderingPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopOrderingPolicy::class);
    }

    public function findOneByShop(Shop $shop): ?ShopOrderingPolicy
    {
        return $this->findOneBy(['shop' => $shop]);
    }

    /**
     * Effective policy value: shops without a row resolve to the default.
     */
    public function resolveMinimumPickupLeadTimeMinutes(Shop $shop): int
    {
        $policy = $this->findOneByShop($shop);

        return null === $policy
            ? ShopOrderingPolicy::DEFAULT_MINIMUM_PICKUP_LEAD_TIME_MINUTES
            : $policy->getMinimumPickupLeadTimeMinutes();
    }
}
