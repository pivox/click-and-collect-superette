<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PickupSlotRule;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PickupSlotRule>
 */
class PickupSlotRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickupSlotRule::class);
    }

    /**
     * @return list<PickupSlotRule>
     */
    public function findActiveForShop(Shop $shop): array
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('IDENTITY(rule.shop) = :shopId')
            ->andWhere('rule.isActive = true')
            ->orderBy('rule.weekday', 'ASC')
            ->addOrderBy('rule.startTime', 'ASC')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->getQuery()
            ->getResult();
    }

    public function findActiveOneForShop(Shop $shop, string $ruleId): ?PickupSlotRule
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.id = :ruleId')
            ->andWhere('IDENTITY(rule.shop) = :shopId')
            ->andWhere('rule.isActive = true')
            ->setParameter('ruleId', $ruleId, 'uuid')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasActiveDuplicate(
        Shop $shop,
        int $weekday,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        ?PickupSlotRule $excludeRule = null,
    ): bool {
        foreach ($this->findActiveForShop($shop) as $rule) {
            if (null !== $excludeRule && $rule->getId()->equals($excludeRule->getId())) {
                continue;
            }

            if (
                $rule->getWeekday() === $weekday
                && $rule->getStartTime()->format('H:i') === $startTime->format('H:i')
                && $rule->getEndTime()->format('H:i') === $endTime->format('H:i')
            ) {
                return true;
            }
        }

        return false;
    }
}
