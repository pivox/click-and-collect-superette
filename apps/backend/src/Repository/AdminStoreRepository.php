<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;

final readonly class AdminStoreRepository
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantProductRepository $merchantProductRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
    ) {
    }

    /**
     * @return list<Shop>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        /** @var list<Shop> $stores */
        $stores = $this->shopRepository->createQueryBuilder('shop')
            ->leftJoin('shop.owner', 'owner')
            ->addSelect('owner')
            ->leftJoin('shop.theme', 'theme')
            ->addSelect('theme')
            ->orderBy('shop.createdAt', 'DESC')
            ->addOrderBy('shop.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $stores;
    }

    public function countAll(): int
    {
        return (int) $this->shopRepository->createQueryBuilder('shop')
            ->select('COUNT(shop.id)')
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

        $counts = [];
        foreach ($this->merchantProductRepository->findBy(['shop' => $stores]) as $merchantProduct) {
            $storeId = $merchantProduct->getShop()->getId()->toRfc4122();
            $counts[$storeId] = ($counts[$storeId] ?? 0) + 1;
        }

        return $counts;
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
