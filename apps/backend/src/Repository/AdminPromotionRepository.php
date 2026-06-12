<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantProduct;
use Doctrine\ORM\QueryBuilder;

final readonly class AdminPromotionRepository
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';

    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
    ) {
    }

    /**
     * @return list<MerchantProduct>
     */
    public function findPaginated(int $limit, int $offset, ?string $status = null, ?string $storeId = null, ?string $merchantId = null): array
    {
        $queryBuilder = $this->createFilteredQueryBuilder($status, $storeId, $merchantId)
            ->addSelect('shop', 'owner', 'productReference', 'localProduct')
            ->orderBy('mp.promotionEndsOn', 'DESC')
            ->addOrderBy('mp.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<MerchantProduct> $products */
        $products = $queryBuilder
            ->getQuery()
            ->getResult();

        return $products;
    }

    public function countFiltered(?string $status = null, ?string $storeId = null, ?string $merchantId = null): int
    {
        return (int) $this->createFilteredQueryBuilder($status, $storeId, $merchantId)
            ->select('COUNT(mp.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createFilteredQueryBuilder(?string $status, ?string $storeId, ?string $merchantId): QueryBuilder
    {
        $queryBuilder = $this->merchantProductRepository->createQueryBuilder('mp')
            ->join('mp.shop', 'shop')
            ->leftJoin('shop.owner', 'owner')
            ->leftJoin('mp.productReference', 'productReference')
            ->leftJoin('mp.localProduct', 'localProduct')
            ->andWhere('mp.promotionPriceTnd IS NOT NULL')
            ->andWhere('mp.promotionEndsOn IS NOT NULL');

        if (null !== $status) {
            // Active/expired boundary follows MerchantProduct::isPromotionActive():
            // a promotion ending today is still active until end of day in Africa/Tunis.
            $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Tunis'));
            $queryBuilder
                ->andWhere(\sprintf('mp.promotionEndsOn %s :today', self::STATUS_ACTIVE === $status ? '>=' : '<'))
                ->setParameter('today', $today, 'date_immutable');
        }

        if (null !== $storeId) {
            $queryBuilder
                ->andWhere('shop.id = :storeId')
                ->setParameter('storeId', $storeId, 'uuid');
        }

        if (null !== $merchantId) {
            $queryBuilder
                ->andWhere('owner.id = :merchantId')
                ->setParameter('merchantId', $merchantId, 'uuid');
        }

        return $queryBuilder;
    }
}
