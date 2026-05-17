<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

final readonly class AdminMerchantRepository
{
    public function __construct(
        private UserRepository $userRepository,
        private ShopRepository $shopRepository,
    ) {
    }

    /**
     * @return list<User>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /** @var list<User> $merchants */
        $merchants = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_MERCHANT%')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $merchants;
    }

    public function countAll(): int
    {
        return (int) $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_MERCHANT%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOne(string $id): ?User
    {
        /** @var User|null $user */
        $user = $this->userRepository->createQueryBuilder('u')
            ->where('u.id = :id')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('role', '%ROLE_MERCHANT%')
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

    public function countStores(User $merchant): int
    {
        return (int) $this->shopRepository->count(['owner' => $merchant]);
    }

    /**
     * @param list<User> $merchants
     *
     * @return array<string, int>
     */
    public function countStoresGrouped(array $merchants): array
    {
        if ([] === $merchants) {
            return [];
        }

        $counts = [];
        $shops = $this->shopRepository->findBy(['owner' => $merchants]);
        foreach ($shops as $shop) {
            $ownerId = $shop->getOwner()?->getId()->toRfc4122();
            if (null === $ownerId) {
                continue;
            }

            $counts[$ownerId] = ($counts[$ownerId] ?? 0) + 1;
        }

        return $counts;
    }
}
