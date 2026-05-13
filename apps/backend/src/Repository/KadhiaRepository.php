<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Kadhia>
 */
class KadhiaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kadhia::class);
    }

    public function findDraftByCustomerAndShop(User $customer, Shop $shop): ?Kadhia
    {
        return $this->findOneBy([
            'customer' => $customer,
            'shop' => $shop,
            'status' => KadhiaStatus::Draft,
        ]);
    }

    public function findByIdAndCustomer(string $kadhiaId, User $customer): ?Kadhia
    {
        return $this->findOneBy(['id' => $kadhiaId, 'customer' => $customer]);
    }

    /**
     * @return list<Kadhia>
     */
    public function findByCustomerWithFilters(
        User $customer,
        ?string $status,
        ?string $shopId,
        int $page,
        int $perPage,
    ): array {
        $qb = $this->createQueryBuilder('k')
            ->where('k.customer = :customer')
            ->setParameter('customer', $customer->getId(), 'uuid')
            ->orderBy('k.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (null !== $status) {
            $parsed = KadhiaStatus::tryFrom($status);
            if (null !== $parsed) {
                $qb->andWhere('k.status = :status')->setParameter('status', $parsed);
            }
        }

        if (null !== $shopId) {
            $qb->andWhere('k.shop = :shopId')->setParameter('shopId', $shopId);
        }

        /* @var list<Kadhia> */
        return $qb->getQuery()->getResult();
    }

    public function countByCustomerWithFilters(
        User $customer,
        ?string $status,
        ?string $shopId,
    ): int {
        $qb = $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.customer = :customer')
            ->setParameter('customer', $customer->getId(), 'uuid');

        if (null !== $status) {
            $parsed = KadhiaStatus::tryFrom($status);
            if (null !== $parsed) {
                $qb->andWhere('k.status = :status')->setParameter('status', $parsed);
            }
        }

        if (null !== $shopId) {
            $qb->andWhere('k.shop = :shopId')->setParameter('shopId', $shopId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
