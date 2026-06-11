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
use Symfony\Component\Uid\Uuid;

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

    /**
     * Returns all matching products for the merchant catalog, filtered and sorted.
     * Suitable for pagination via array_slice in the provider.
     *
     * @return list<MerchantProduct>
     */
    public function filterCatalogForShop(
        Shop $shop,
        ?string $q = null,
        ?string $availability = null,
        ?string $visibility = null,
        ?string $completion = null,
        ?string $category = null,
        ?string $promotion = null,
    ): array {
        $criteria = ['shop' => $shop];

        if ('available' === $availability) {
            $criteria['isAvailable'] = true;
        } elseif ('unavailable' === $availability) {
            $criteria['isAvailable'] = false;
        }

        if ('visible' === $visibility) {
            $criteria['isVisible'] = true;
        } elseif ('hidden' === $visibility) {
            $criteria['isVisible'] = false;
        }

        $products = $this->findBy($criteria);

        $normalizedQ = $this->normalizeSearchValue($q);
        $normalizedCategory = $this->normalizeSearchValue($category);

        if (null !== $normalizedQ || null !== $normalizedCategory) {
            $products = array_values(array_filter(
                $products,
                function (MerchantProduct $product) use ($normalizedQ, $normalizedCategory): bool {
                    if (null !== $normalizedCategory) {
                        $merchantCategory = $product->getActiveMerchantCategory();
                        $categoryName = null !== $merchantCategory
                            ? $merchantCategory->getNameFr()
                            : $product->getDisplayCategoryName();
                        if (trim($this->normalizeSearchText($categoryName)) !== $normalizedCategory) {
                            return false;
                        }
                    }

                    if (null !== $normalizedQ) {
                        return $this->matchesMerchantCatalogQuery($product, $normalizedQ);
                    }

                    return true;
                },
            ));
        }

        if ('needs_price' === $completion) {
            $products = array_values(array_filter(
                $products,
                static fn (MerchantProduct $product): bool => 0 === bccomp($product->getPriceTnd(), '0.000', 3),
            ));
        }

        if ('active' === $promotion) {
            $products = array_values(array_filter(
                $products,
                static fn (MerchantProduct $product): bool => $product->isPromotionActive(),
            ));
        }

        usort(
            $products,
            static fn (MerchantProduct $a, MerchantProduct $b): int => [
                $a->getDisplayNameFr(),
                $a->getDisplayBrandName() ?? '',
            ] <=> [
                $b->getDisplayNameFr(),
                $b->getDisplayBrandName() ?? '',
            ],
        );

        return $products;
    }

    public function findOneForShopAndProductReference(Shop $shop, ProductReference $productReference): ?MerchantProduct
    {
        return $this->findOneBy([
            'shop' => $shop,
            'productReference' => $productReference,
        ]);
    }

    public function findOneLocalForShopAndBarcode(Shop $shop, string $barcode): ?MerchantProduct
    {
        $barcode = trim($barcode);
        if ('' === $barcode) {
            return null;
        }

        foreach ($this->findCatalogForShop($shop) as $candidate) {
            if ($barcode === trim((string) $candidate->getLocalProduct()?->getBarcode())) {
                return $candidate;
            }
        }

        return null;
    }

    public function findOneLocalForShopAndIdentity(
        Shop $shop,
        string $brandName,
        string $nameFr,
        string $volume,
        ProductUnit $unit,
    ): ?MerchantProduct {
        $normalizedBrandName = $this->normalizeSearchValue($brandName);
        $normalizedNameFr = $this->normalizeSearchValue($nameFr);

        foreach ($this->findCatalogForShop($shop) as $candidate) {
            $localProduct = $candidate->getLocalProduct();
            if (
                null !== $localProduct?->getVolume()
                && $normalizedBrandName === $this->normalizeSearchValue($localProduct->getBrandName())
                && $normalizedNameFr === $this->normalizeSearchValue($localProduct->getNameFr())
                && $unit === $localProduct->getUnit()
                && 0 === bccomp($localProduct->getVolume(), $volume, 3)
            ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<string> $merchantProductIds
     *
     * @return list<MerchantProduct>
     */
    public function findForShopAndIds(Shop $shop, array $merchantProductIds): array
    {
        if ([] === $merchantProductIds) {
            return [];
        }

        $ids = array_map(static fn (string $merchantProductId): Uuid => Uuid::fromString($merchantProductId), $merchantProductIds);

        /** @var list<MerchantProduct> $merchantProducts */
        $merchantProducts = $this->findBy([
            'shop' => $shop,
            'id' => $ids,
        ]);

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
                if (bccomp($merchantProduct->getPriceTnd(), '0.000', 3) <= 0) {
                    return false;
                }

                $productReference = $merchantProduct->getProductReference();

                if (null !== $productReference && ProductReferenceStatus::Approved !== $productReference->getStatus()) {
                    return false;
                }

                if (null !== $normalizedCategorySlug && strtolower($merchantProduct->getDisplayCategorySlug()) !== $normalizedCategorySlug) {
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
                $left->getDisplayNameFr(),
                $left->getDisplayBrandName() ?? '',
            ] <=> [
                $right->getDisplayNameFr(),
                $right->getDisplayBrandName() ?? '',
            ],
        );

        return $merchantProducts;
    }

    private function matchesMerchantCatalogQuery(MerchantProduct $product, string $normalizedQuery): bool
    {
        $merchantCategory = $product->getActiveMerchantCategory();
        /* @var list<string> $candidates */
        $candidates = array_values(array_filter(
            [
                $product->getDisplayNameFr(),
                $product->getDisplayBrandName(),
                $product->getDisplayCategoryName(),
                $product->getMerchantNote(),
                $merchantCategory?->getNameFr(),
            ],
            static fn (?string $term): bool => null !== $term && '' !== trim($term),
        ));

        foreach ($candidates as $term) {
            if (str_contains($this->normalizeSearchText($term), $normalizedQuery)) {
                return true;
            }
        }

        return false;
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
        $terms = [
            $merchantProduct->getDisplayNameFr(),
            $merchantProduct->getDisplayUnit()->value,
        ];

        $brand = $merchantProduct->getDisplayBrandName();
        if (null !== $brand && '' !== trim($brand)) {
            $terms[] = $brand;
        }

        $productReference = $merchantProduct->getProductReference();
        $optionalTerms = [
            $merchantProduct->getDisplayNameAr(),
            $merchantProduct->getDisplayVolume(),
        ];
        if (null !== $productReference) {
            $optionalTerms[] = $productReference->getVariantFr();
            $optionalTerms[] = $productReference->getVariantAr();
        }

        foreach ($optionalTerms as $optionalTerm) {
            if (null !== $optionalTerm && '' !== trim($optionalTerm)) {
                $terms[] = $optionalTerm;
            }
        }

        $compactFormat = $this->buildCompactFormat($merchantProduct->getDisplayVolume(), $merchantProduct->getDisplayUnit());
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
