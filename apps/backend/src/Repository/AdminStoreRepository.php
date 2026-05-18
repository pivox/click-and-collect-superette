<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AdminStoreRepository
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantProductRepository $merchantProductRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Shop>
     */
    public function findPaginated(int $limit, int $offset, ?bool $isActive = null): array
    {
        $queryBuilder = $this->shopRepository->createQueryBuilder('shop')
            ->leftJoin('shop.owner', 'owner')
            ->addSelect('owner')
            ->leftJoin('shop.theme', 'theme')
            ->addSelect('theme')
            ->orderBy('shop.createdAt', 'DESC')
            ->addOrderBy('shop.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $isActive) {
            $queryBuilder
                ->andWhere('shop.active = :isActive')
                ->setParameter('isActive', $isActive);
        }

        /** @var list<Shop> $stores */
        $stores = $queryBuilder
            ->getQuery()
            ->getResult();

        return $stores;
    }

    public function countAll(?bool $isActive = null): int
    {
        $queryBuilder = $this->shopRepository->createQueryBuilder('shop')
            ->select('COUNT(shop.id)');

        if (null !== $isActive) {
            $queryBuilder
                ->andWhere('shop.active = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOne(string $id): ?Shop
    {
        /** @var Shop|null $shop */
        $shop = $this->shopRepository->createQueryBuilder('shop')
            ->leftJoin('shop.owner', 'owner')
            ->addSelect('owner')
            ->leftJoin('shop.theme', 'theme')
            ->addSelect('theme')
            ->andWhere('shop.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $shop;
    }

    public function findOneBySlug(string $slug): ?Shop
    {
        return $this->shopRepository->findOneBy(['slug' => $slug]);
    }

    public function save(Shop $shop): void
    {
        $this->entityManager->persist($shop);
        $this->entityManager->flush();
    }

    /**
     * @param list<Shop> $stores
     *
     * @return array<string, int>
     */
    public function countProductsGrouped(array $stores): array
    {
        if ([] === $stores) {
            return [];
        }

        /** @var list<array{shop_id: mixed, products_count: string|int}> $rows */
        $rows = $this->entityManager->getConnection()->executeQuery(
            \sprintf(
                'SELECT shop_id, COUNT(id) AS products_count FROM merchant_products WHERE shop_id IN (%s) GROUP BY shop_id',
                implode(', ', array_fill(0, \count($stores), '?')),
            ),
            array_map(static fn (Shop $shop): string => $shop->getId()->toRfc4122(), $stores),
            array_fill(0, \count($stores), 'uuid'),
        )->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $shopId = $this->normalizeShopId($row['shop_id']);
            $counts[$shopId] = (int) $row['products_count'];
        }

        return $counts;
    }

    private function normalizeShopId(mixed $shopId): string
    {
        if ($shopId instanceof Uuid) {
            return $shopId->toRfc4122();
        }

        if (\is_string($shopId) && 16 === \strlen($shopId)) {
            return Uuid::fromBinary($shopId)->toRfc4122();
        }

        return (string) $shopId;
    }

    public function countProducts(Shop $shop): int
    {
        return (int) $this->merchantProductRepository->count(['shop' => $shop]);
    }

    public function countActiveExceptionalClosures(Shop $shop): int
    {
        return (int) $this->exceptionalClosureRepository->count(['shop' => $shop, 'isActive' => true]);
    }

    public function countActivePickupRules(Shop $shop): int
    {
        return (int) $this->pickupSlotRuleRepository->count(['shop' => $shop, 'isActive' => true]);
    }
}
