<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\User;
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
    public function findActiveByOwner(User $owner, int $limit = 20): array
    {
        /* @var list<Shop> */
        return $this->findBy(
            ['owner' => $owner, 'active' => true],
            ['createdAt' => 'ASC'],
            $limit,
        );
    }

    /**
     * Returns active stores matching the given search criteria.
     * Returns an empty list when both parameters are null.
     *
     * @return list<Shop>
     */
    public function findActiveBySearchCriteria(?string $query, ?string $city): array
    {
        if (null === $query && null === $city) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->orderBy('s.name', 'ASC');

        if (null !== $query) {
            $qb->andWhere('LOWER(s.name) LIKE LOWER(:query) OR LOWER(s.city) LIKE LOWER(:query)')
               ->setParameter('query', '%'.$query.'%');
        }

        if (null !== $city) {
            $qb->andWhere('LOWER(s.city) = LOWER(:city)')
               ->setParameter('city', $city);
        }

        /* @var list<Shop> */
        return $qb->getQuery()->getResult();
    }
}
