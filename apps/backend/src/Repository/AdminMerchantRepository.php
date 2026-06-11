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
    public function findPaginated(int $limit, int $offset, ?string $crmStatus = null, ?string $crmOwner = null): array
    {
        $ids = $this->fetchMerchantIds($limit, $offset, $crmStatus, $crmOwner);
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
        return $this->countFiltered();
    }

    public function countFiltered(?string $crmStatus = null, ?string $crmOwner = null): int
    {
        [$joinSql, $whereSql, $params] = $this->crmFilterSql($crmStatus, $crmOwner);

        return (int) $this->connection()->executeQuery(
            \sprintf('SELECT COUNT(u.id) FROM users u%s WHERE %s%s', $joinSql, $this->roleExpr('u'), $whereSql),
            ['role' => '%ROLE_MERCHANT%'] + $params,
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
    private function fetchMerchantIds(int $limit, int $offset, ?string $crmStatus = null, ?string $crmOwner = null): array
    {
        [$joinSql, $whereSql, $params] = $this->crmFilterSql($crmStatus, $crmOwner);

        /* @var list<string> */
        return $this->connection()->executeQuery(
            \sprintf(
                'SELECT u.id FROM users u%s WHERE %s%s ORDER BY u.created_at DESC, u.id DESC LIMIT :limit OFFSET :offset',
                $joinSql,
                $this->roleExpr('u'),
                $whereSql,
            ),
            ['role' => '%ROLE_MERCHANT%', 'limit' => $limit, 'offset' => $offset] + $params,
        )->fetchFirstColumn();
    }

    /**
     * @return array{string, string, array<string, string>}
     */
    private function crmFilterSql(?string $crmStatus, ?string $crmOwner): array
    {
        if (null === $crmStatus && null === $crmOwner) {
            return ['', '', []];
        }

        $joinSql = ' LEFT JOIN merchant_crm_profiles mcp ON mcp.merchant_id = u.id';
        $whereSql = '';
        $params = [];

        if (null !== $crmStatus) {
            // A merchant without a CRM profile is an implicit prospect.
            $whereSql .= ' AND COALESCE(mcp.status, \'prospect\') = :crm_status';
            $params['crm_status'] = $crmStatus;
        }

        if (null !== $crmOwner) {
            $whereSql .= ' AND mcp.commercial_owner = :crm_owner';
            $params['crm_owner'] = $crmOwner;
        }

        return [$joinSql, $whereSql, $params];
    }

    private function roleExpr(string $alias = ''): string
    {
        $column = '' !== $alias ? $alias.'.roles' : 'roles';

        // PostgreSQL json column does not support LIKE — cast to text first.
        // SQLite stores json as plain text, so LIKE works directly.
        return $this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? $column.'::text LIKE :role'
            : $column.' LIKE :role';
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}
