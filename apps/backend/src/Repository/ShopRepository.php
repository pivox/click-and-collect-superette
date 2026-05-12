<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shop>
 */
class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    public function findActiveByQrCodeToken(string $token): ?Shop
    {
        return $this->createQueryBuilder('s')
            ->where('s.qrCodeToken = :token')
            ->andWhere('s.active = true')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Shop>
     */
    public function findActiveByNameOrCity(string $query, ?string $city = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->andWhere('LOWER(s.name) LIKE LOWER(:query) OR LOWER(s.city) LIKE LOWER(:query)')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('s.name', 'ASC');

        if (null !== $city) {
            $qb->andWhere('LOWER(s.city) = LOWER(:city)')
               ->setParameter('city', $city);
        }

        /* @var list<Shop> */
        return $qb->getQuery()->getResult();
    }
}
