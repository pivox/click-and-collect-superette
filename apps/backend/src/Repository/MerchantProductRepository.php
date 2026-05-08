<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantProduct>
 */
class MerchantProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantProduct::class);
    }

    /**
     * @return list<MerchantProduct>
     */
    public function findCatalogForShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    public function findOneForShopAndProductReference(Shop $shop, ProductReference $productReference): ?MerchantProduct
    {
        return $this->findOneBy([
            'shop' => $shop,
            'productReference' => $productReference,
        ]);
    }

    /**
     * @return list<MerchantProduct>
     */
    public function findPublicCatalogForShop(Shop $shop, ?string $query = null, ?string $categorySlug = null): array
    {
        $normalizedQuery = $this->normalizeSearchValue($query);
        $normalizedCategorySlug = $this->normalizeSearchValue($categorySlug);

        $merchantProducts = $this->findBy([
            'shop' => $shop,
            'isVisible' => true,
            'isAvailable' => true,
        ]);

        $merchantProducts = array_filter(
            $merchantProducts,
            static function (MerchantProduct $merchantProduct) use ($normalizedQuery, $normalizedCategorySlug): bool {
                $productReference = $merchantProduct->getProductReference();
                $brand = $productReference->getBrand();
                $category = $productReference->getCategory();

                if (null !== $normalizedCategorySlug && strtolower($category->getSlug()) !== $normalizedCategorySlug) {
                    return false;
                }

                if (null === $normalizedQuery) {
                    return true;
                }

                return str_contains(strtolower($productReference->getNameFr()), $normalizedQuery)
                    || str_contains(strtolower($brand->getCanonicalName()), $normalizedQuery);
            },
        );

        usort(
            $merchantProducts,
            static fn (MerchantProduct $left, MerchantProduct $right): int => [
                $left->getProductReference()->getNameFr(),
                $left->getProductReference()->getBrand()->getCanonicalName(),
            ] <=> [
                $right->getProductReference()->getNameFr(),
                $right->getProductReference()->getBrand()->getCanonicalName(),
            ],
        );

        return $merchantProducts;
    }

    private function normalizeSearchValue(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim(strtolower($value));

        return '' === $value ? null : $value;
    }
}
