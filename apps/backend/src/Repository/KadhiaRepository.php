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
        return $this->findByIdAndMember($kadhiaId, $customer);
    }

    public function findByIdAndMember(string $kadhiaId, User $member): ?Kadhia
    {
        return $this->createQueryBuilder('k')
            ->innerJoin('k.members', 'm')
            ->andWhere('k.id = :kadhiaId')
            ->andWhere('m.user = :member')
            ->setParameter('kadhiaId', $kadhiaId, 'uuid')
            ->setParameter('member', $member->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
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
            ->innerJoin('k.members', 'm')
            ->where('m.user = :customer')
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
            $qb->andWhere('k.shop = :shopId')->setParameter('shopId', $shopId, 'uuid');
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
            ->innerJoin('k.members', 'm')
            ->where('m.user = :customer')
            ->setParameter('customer', $customer->getId(), 'uuid');

        if (null !== $status) {
            $parsed = KadhiaStatus::tryFrom($status);
            if (null !== $parsed) {
                $qb->andWhere('k.status = :status')->setParameter('status', $parsed);
            }
        }

        if (null !== $shopId) {
            $qb->andWhere('k.shop = :shopId')->setParameter('shopId', $shopId, 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
