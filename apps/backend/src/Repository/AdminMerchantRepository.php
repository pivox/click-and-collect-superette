<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminMerchantRepository
{
    public function __construct(
        private UserRepository $userRepository,
        private ShopRepository $shopRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<User>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        $ids = $this->fetchMerchantIds($limit, $offset);
        if ([] === $ids) {
            return [];
        }

        /** @var list<User> $users */
        $users = $this->userRepository->findBy(['id' => $ids]);

        // findBy does not guarantee SQL ordering — re-apply (created_at DESC, id DESC).
        usort($users, static function (User $a, User $b): int {
            $cmp = $b->getCreatedAt() <=> $a->getCreatedAt();

            return 0 !== $cmp ? $cmp : strcmp($b->getId()->toRfc4122(), $a->getId()->toRfc4122());
        });

        return $users;
    }

    public function countAll(): int
    {
        return (int) $this->connection()->executeQuery(
            \sprintf('SELECT COUNT(id) FROM users WHERE %s', $this->roleExpr()),
            ['role' => '%ROLE_MERCHANT%'],
        )->fetchOne();
    }

    public function findOne(string $id): ?User
    {
        $user = $this->userRepository->find($id);
        if (null === $user || !\in_array('ROLE_MERCHANT', $user->getRoles(), true)) {
            return null;
        }

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

    /** @return list<string> */
    private function fetchMerchantIds(int $limit, int $offset): array
    {
        /* @var list<string> */
        return $this->connection()->executeQuery(
            \sprintf(
                'SELECT id FROM users WHERE %s ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset',
                $this->roleExpr(),
            ),
            ['role' => '%ROLE_MERCHANT%', 'limit' => $limit, 'offset' => $offset],
        )->fetchFirstColumn();
    }

    private function roleExpr(): string
    {
        // PostgreSQL json column does not support LIKE — cast to text first.
        // SQLite stores json as plain text, so LIKE works directly.
        return $this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? 'roles::text LIKE :role'
            : 'roles LIKE :role';
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}
