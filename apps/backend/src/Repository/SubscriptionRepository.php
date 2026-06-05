<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionLifecycle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findOneByMerchant(User $merchant): ?Subscription
    {
        return $this->findOneBy(['merchant' => $merchant]);
    }

    /**
     * @param list<User> $merchants
     *
     * @return array<string, Subscription>
     */
    public function findByMerchantsIndexed(array $merchants): array
    {
        if ([] === $merchants) {
            return [];
        }

        /** @var list<Subscription> $subscriptions */
        $subscriptions = $this->findBy(['merchant' => $merchants]);
        $indexed = [];
        foreach ($subscriptions as $subscription) {
            $indexed[$subscription->getMerchant()->getId()->toRfc4122()] = $subscription;
        }

        return $indexed;
    }

    /**
     * @return list<Subscription>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /* @var list<Subscription> */
        return $this->createQueryBuilder('s')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Subscription>
     */
    public function findPricingPhaseRefreshCandidates(\DateTimeImmutable $now): array
    {
        /* @var list<Subscription> */
        return $this->createQueryBuilder('s')
            ->andWhere('s.nextPhaseChangeAt IS NOT NULL')
            ->andWhere('s.nextPhaseChangeAt <= :now')
            ->setParameter('now', $now)
            ->addOrderBy('s.nextPhaseChangeAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Subscription>
     */
    public function findPaymentReminderCandidates(): array
    {
        /* @var list<Subscription> */
        return $this->createQueryBuilder('s')
            ->andWhere('s.lifecycle IN (:lifecycles)')
            ->andWhere('s.monthlyPriceTnd > :zero')
            ->setParameter('lifecycles', [
                SubscriptionLifecycle::Active,
                SubscriptionLifecycle::PaymentDue,
                SubscriptionLifecycle::GracePeriod,
            ])
            ->setParameter('zero', '0.000')
            ->addOrderBy('s.currentPeriodEndsAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
