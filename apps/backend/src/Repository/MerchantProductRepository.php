<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
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
     * @param list<string> $merchantProductIds
     *
     * @return list<MerchantProduct>
     */
    public function findForShopAndIds(Shop $shop, array $merchantProductIds): array
    {
        $merchantProducts = [];

        foreach ($merchantProductIds as $merchantProductId) {
            $merchantProduct = $this->find($merchantProductId);
            if ($merchantProduct instanceof MerchantProduct && $merchantProduct->getShop()->getId()->equals($shop->getId())) {
                $merchantProducts[] = $merchantProduct;
            }
        }

        return $merchantProducts;
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
            function (MerchantProduct $merchantProduct) use ($normalizedQuery, $normalizedCategorySlug): bool {
                $productReference = $merchantProduct->getProductReference();
                $brand = $productReference->getBrand();
                $category = $productReference->getCategory();

                if (ProductReferenceStatus::Approved !== $productReference->getStatus()) {
                    return false;
                }

                if (null !== $normalizedCategorySlug && strtolower($category->getSlug()) !== $normalizedCategorySlug) {
                    return false;
                }

                if (null === $normalizedQuery) {
                    return true;
                }

                return $this->matchesPublicCatalogQuery($merchantProduct, $normalizedQuery);
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

        $value = trim($this->normalizeSearchText($value));

        return '' === $value ? null : $value;
    }

    private function matchesPublicCatalogQuery(MerchantProduct $merchantProduct, string $normalizedQuery): bool
    {
        foreach ($this->buildPublicSearchTerms($merchantProduct) as $term) {
            if (str_contains($this->normalizeSearchText($term), $normalizedQuery)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function buildPublicSearchTerms(MerchantProduct $merchantProduct): array
    {
        $productReference = $merchantProduct->getProductReference();
        $terms = [
            $productReference->getNameFr(),
            $productReference->getBrand()->getCanonicalName(),
            $productReference->getUnit()->value,
        ];

        foreach ([$productReference->getNameAr(), $productReference->getVariantFr(), $productReference->getVariantAr(), $productReference->getVolume()] as $optionalTerm) {
            if (null !== $optionalTerm && '' !== trim($optionalTerm)) {
                $terms[] = $optionalTerm;
            }
        }

        $compactFormat = $this->buildCompactFormat($productReference->getVolume(), $productReference->getUnit());
        if (null !== $compactFormat) {
            $terms[] = $compactFormat;
        }

        return $terms;
    }

    private function buildCompactFormat(?string $volume, ProductUnit $unit): ?string
    {
        if (null === $volume) {
            return null;
        }

        $normalizedVolume = rtrim(rtrim($volume, '0'), '.');
        if ('' === $normalizedVolume) {
            return null;
        }

        return match ($unit) {
            ProductUnit::Litre => $normalizedVolume.'l',
            ProductUnit::Millilitre => $normalizedVolume.'ml',
            ProductUnit::Kilogramme => $normalizedVolume.'kg',
            ProductUnit::Gramme => $normalizedVolume.'g',
            ProductUnit::Piece => $normalizedVolume.'pc',
            ProductUnit::Paquet => $normalizedVolume.'pq',
        };
    }

    private function normalizeSearchText(string $value): string
    {
        $value = strtolower($value);
        $value = strtr($value, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (false !== $transliterated && '' !== trim($transliterated, " ?\t\n\r\0\x0B")) {
            return strtolower($transliterated);
        }

        return $value;
    }
}
