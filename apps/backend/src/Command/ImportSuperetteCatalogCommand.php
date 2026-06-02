<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(
    name: 'app:products:import-superette-catalog',
    description: 'Import the local Tunisian supérette catalog JSON into product references and optional shop catalogs',
)]
final class ImportSuperetteCatalogCommand extends Command
{
    private const GENERIC_BRAND_NAME = 'Marque non vérifiée';
    private const GENERIC_BRAND_SLUG = 'marque-non-verifiee';
    private const BATCH_SIZE = 250;

    /** @var array<string, Brand> */
    private array $brandCache = [];

    /** @var array<string, Category> */
    private array $categoryCache = [];

    private AsciiSlugger $slugger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
        $this->slugger = new AsciiSlugger('fr');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('catalogPath', InputArgument::REQUIRED, 'Path to catalogue_superette_tunisie_v1.0.0.json')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Delete existing Kadhia, orders, merchant catalog and product reference data before import')
            ->addOption('sync-shop-catalogs', null, InputOption::VALUE_NONE, 'Create or update MerchantProduct rows for all active supérettes')
            ->addOption('reference-status', null, InputOption::VALUE_REQUIRED, 'Imported ProductReference status: draft|pending_review|approved', ProductReferenceStatus::Approved->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1024M');

        $io = new SymfonyStyle($input, $output);
        $catalogPath = (string) $input->getArgument('catalogPath');
        $reset = (bool) $input->getOption('reset');
        $syncShopCatalogs = (bool) $input->getOption('sync-shop-catalogs');
        $referenceStatus = ProductReferenceStatus::tryFrom((string) $input->getOption('reference-status'));

        if (null === $referenceStatus || !\in_array($referenceStatus, [
            ProductReferenceStatus::Draft,
            ProductReferenceStatus::PendingReview,
            ProductReferenceStatus::Approved,
        ], true)) {
            $io->error('Invalid --reference-status. Allowed values: draft, pending_review, approved.');

            return Command::FAILURE;
        }

        if (!is_file($catalogPath) || !is_readable($catalogPath)) {
            $io->error(\sprintf('Catalog file is not readable: %s', $catalogPath));

            return Command::FAILURE;
        }

        if ($reset && !\in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('The destructive --reset option is only available in dev/test environments.');

            return Command::FAILURE;
        }

        $payload = $this->readCatalog($catalogPath);
        if (null === $payload) {
            $io->error('Catalog JSON is invalid or does not contain a products array.');

            return Command::FAILURE;
        }

        $stats = [
            'products_imported' => 0,
            'products_updated' => 0,
            'merchant_products_created' => 0,
            'merchant_products_updated' => 0,
            'kadhias_deleted' => 0,
            'orders_deleted' => 0,
        ];

        if ($reset) {
            $stats = array_replace($stats, $this->resetCatalogData());
            $this->entityManager->clear();
        }

        $this->warmCaches();
        $activeShops = $syncShopCatalogs ? $this->findActiveShops() : [];

        foreach ($payload['products'] as $index => $rawProduct) {
            if (!\is_array($rawProduct)) {
                continue;
            }

            [$productReference, $productReferenceCreated] = $this->upsertProductReference($rawProduct, $referenceStatus);
            $this->entityManager->persist($productReference);
            $productReferenceCreated ? ++$stats['products_imported'] : ++$stats['products_updated'];

            foreach ($activeShops as $shop) {
                [, $merchantProductCreated] = $this->upsertMerchantProduct($shop, $productReference, $rawProduct);
                $merchantProductCreated ? ++$stats['merchant_products_created'] : ++$stats['merchant_products_updated'];
            }

            if (0 === (($index + 1) % self::BATCH_SIZE)) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        $io->success('Supérette catalog import complete.');
        $io->definitionList(
            ['products_imported' => (string) $stats['products_imported']],
            ['products_updated' => (string) $stats['products_updated']],
            ['merchant_products_created' => (string) $stats['merchant_products_created']],
            ['merchant_products_updated' => (string) $stats['merchant_products_updated']],
            ['kadhias_deleted' => (string) $stats['kadhias_deleted']],
            ['orders_deleted' => (string) $stats['orders_deleted']],
        );

        return Command::SUCCESS;
    }

    /**
     * @return array{products: list<array<string, mixed>>}|null
     */
    private function readCatalog(string $catalogPath): ?array
    {
        try {
            $content = file_get_contents($catalogPath);
            if (false === $content) {
                return null;
            }

            $payload = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($payload) || !isset($payload['products']) || !\is_array($payload['products'])) {
            return null;
        }

        /** @var list<array<string, mixed>> $products */
        $products = array_values(array_filter($payload['products'], \is_array(...)));

        return ['products' => $products];
    }

    /**
     * @return array{kadhias_deleted: int, orders_deleted: int}
     */
    private function resetCatalogData(): array
    {
        $connection = $this->entityManager->getConnection();
        $ordersDeleted = $this->countRows($connection, 'orders');
        $kadhiasDeleted = $this->countRows($connection, 'kadhias');

        foreach ([
            'messenger_messages',
            'notifications',
            'pickup_sessions',
            'order_status_logs',
            'order_lines',
            'orders',
            'kadhia_lines',
            'kadhias',
            'merchant_product_price_history',
            'merchant_products',
            'product_reference_proposals',
            'product_references',
            'product_families',
            'categories',
            'brands',
        ] as $tableName) {
            if ($this->tableExists($connection, $tableName)) {
                $connection->executeStatement('DELETE FROM '.$tableName);
            }
        }

        return [
            'kadhias_deleted' => $kadhiasDeleted,
            'orders_deleted' => $ordersDeleted,
        ];
    }

    private function tableExists(Connection $connection, string $tableName): bool
    {
        return \in_array($tableName, array_map('strtolower', $connection->createSchemaManager()->listTableNames()), true);
    }

    private function countRows(Connection $connection, string $tableName): int
    {
        if (!$this->tableExists($connection, $tableName)) {
            return 0;
        }

        return (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$tableName);
    }

    /**
     * @param array<string, mixed> $rawProduct
     *
     * @return array{0: ProductReference, 1: bool}
     */
    private function upsertProductReference(array $rawProduct, ProductReferenceStatus $referenceStatus): array
    {
        $nameFr = $this->cleanString($rawProduct['name_fr'] ?? null, 255) ?? 'Produit sans nom';
        $nameAr = $this->cleanString($rawProduct['name_ar'] ?? null, 255);
        $brand = $this->resolveBrand($this->cleanString($rawProduct['brand'] ?? null, 160));
        $category = $this->resolveCategory(
            $this->cleanString($rawProduct['category'] ?? null, 160) ?? 'Divers',
            $this->cleanString($rawProduct['subcategory'] ?? null, 160),
        );
        [$volume, $unit] = $this->parseUnit($this->cleanString($rawProduct['unit'] ?? null, 80));
        $barcode = $this->resolveBarcode($rawProduct);

        $existing = null !== $barcode
            ? $this->entityManager->getRepository(ProductReference::class)->findOneBy(['barcode' => $barcode])
            : $this->entityManager->getRepository(ProductReference::class)->findOneBy([
                'nameFr' => $nameFr,
                'brand' => $brand,
                'category' => $category,
                'volume' => $volume,
                'unit' => $unit,
            ]);

        $created = !$existing instanceof ProductReference;
        $productReference = $created ? new ProductReference() : $existing;
        $productReference
            ->setNameFr($nameFr)
            ->setNameAr($nameAr)
            ->setBrand($brand)
            ->setCategory($category)
            ->setVolume($volume)
            ->setUnit($unit)
            ->setBarcode($barcode)
            ->setStatus($referenceStatus)
            ->setAliases($this->buildAliases($rawProduct));

        return [$productReference, $created];
    }

    /**
     * @param array<string, mixed> $rawProduct
     *
     * @return array{0: MerchantProduct, 1: bool}
     */
    private function upsertMerchantProduct(Shop $shop, ProductReference $productReference, array $rawProduct): array
    {
        $existing = $this->entityManager->getRepository(MerchantProduct::class)->findOneBy([
            'shop' => $shop,
            'productReference' => $productReference,
        ]);

        $created = !$existing instanceof MerchantProduct;
        $merchantProduct = $created ? new MerchantProduct() : $existing;
        $merchantProduct
            ->setShop($shop)
            ->setProductReference($productReference);

        if ($created) {
            $merchantProduct
                ->setPriceTnd($this->resolvePriceTnd($rawProduct, $productReference))
                ->setAvailable(true)
                ->setVisible(true);
        }

        $this->entityManager->persist($merchantProduct);

        return [$merchantProduct, $created];
    }

    private function resolveBrand(?string $rawBrand): Brand
    {
        $name = $rawBrand ?? self::GENERIC_BRAND_NAME;
        $slug = null === $rawBrand ? self::GENERIC_BRAND_SLUG : $this->slugify($name, 'marque');

        if (isset($this->brandCache[$slug])) {
            return $this->brandCache[$slug];
        }

        $existing = $this->entityManager->getRepository(Brand::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Brand) {
            $this->brandCache[$slug] = $existing;

            return $existing;
        }

        $brand = (new Brand())
            ->setCanonicalName($name)
            ->setSlug($slug)
            ->setCountry('TN');

        $this->entityManager->persist($brand);
        $this->brandCache[$slug] = $brand;

        return $brand;
    }

    private function resolveCategory(string $rootName, ?string $subcategoryName): Category
    {
        $rootSlug = $this->slugify($rootName, 'categorie');
        $rootCategory = $this->resolveSingleCategory($rootName, $rootSlug, null);

        if (null === $subcategoryName) {
            return $rootCategory;
        }

        $subcategorySlug = $rootSlug.'-'.$this->slugify($subcategoryName, 'sous-categorie');

        return $this->resolveSingleCategory($subcategoryName, $subcategorySlug, $rootCategory);
    }

    private function resolveSingleCategory(string $nameFr, string $slug, ?Category $parent): Category
    {
        if (isset($this->categoryCache[$slug])) {
            return $this->categoryCache[$slug];
        }

        $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Category) {
            $this->categoryCache[$slug] = $existing;

            return $existing;
        }

        $category = (new Category())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setParent($parent)
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->categoryCache[$slug] = $category;

        return $category;
    }

    /**
     * @return array{0: ?string, 1: ProductUnit}
     */
    private function parseUnit(?string $rawUnit): array
    {
        if (null === $rawUnit) {
            return [null, ProductUnit::Piece];
        }

        $normalized = strtolower(str_replace(',', '.', $rawUnit));
        $normalized = strtr($normalized, ['litres' => 'l', 'litre' => 'l', 'grammes' => 'g', 'gramme' => 'g']);

        if (1 === preg_match('/(\d+(?:\.\d+)?)\s*(ml|cl|dl|l|kg|g)\b/u', $normalized, $matches)) {
            $value = (float) $matches[1];
            $unit = match ($matches[2]) {
                'ml' => ProductUnit::Millilitre,
                'cl' => ProductUnit::Millilitre,
                'dl' => ProductUnit::Millilitre,
                'l' => ProductUnit::Litre,
                'kg' => ProductUnit::Kilogramme,
                'g' => ProductUnit::Gramme,
            };

            if ('cl' === $matches[2]) {
                $value *= 10;
            }
            if ('dl' === $matches[2]) {
                $value *= 100;
            }

            return [number_format($value, 3, '.', ''), $unit];
        }

        if (str_contains($normalized, 'paquet') || str_contains($normalized, 'pack')) {
            return [null, ProductUnit::Paquet];
        }

        return [null, ProductUnit::Piece];
    }

    /**
     * @param array<string, mixed> $rawProduct
     *
     * @return list<string>
     */
    private function buildAliases(array $rawProduct): array
    {
        $aliases = [];
        foreach (['sku', 'name_tn_latin'] as $key) {
            $value = $this->cleanString($rawProduct[$key] ?? null, 120);
            if (null !== $value) {
                $aliases[] = $value;
            }
        }

        foreach (['brand_candidates', 'tags'] as $key) {
            if (!isset($rawProduct[$key]) || !\is_array($rawProduct[$key])) {
                continue;
            }

            foreach ($rawProduct[$key] as $value) {
                $alias = $this->cleanString($value, 120);
                if (null !== $alias) {
                    $aliases[] = $alias;
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param array<string, mixed> $rawProduct
     */
    private function resolveBarcode(array $rawProduct): ?string
    {
        $barcode = $this->cleanString($rawProduct['barcode'] ?? null, 64);
        if (null === $barcode && isset($rawProduct['commercial_identity']) && \is_array($rawProduct['commercial_identity'])) {
            $barcode = $this->cleanString($rawProduct['commercial_identity']['gtin'] ?? null, 64);
        }

        if (null === $barcode) {
            return null;
        }

        return preg_match('/^[0-9]{8,14}$/', $barcode) ? $barcode : null;
    }

    /**
     * @param array<string, mixed> $rawProduct
     */
    private function resolvePriceTnd(array $rawProduct, ProductReference $productReference): string
    {
        $estimatedPrice = $rawProduct['estimated_price_tnd'] ?? null;
        if (null !== $estimatedPrice && is_numeric($estimatedPrice) && (float) $estimatedPrice > 0) {
            return number_format((float) $estimatedPrice, 3, '.', '');
        }

        $source = $this->cleanString($rawProduct['sku'] ?? null, 120) ?? $productReference->getNameFr();
        $hash = (int) \sprintf('%u', crc32($source));
        $millimes = 500 + ($hash % 12001);

        return number_format($millimes / 1000, 3, '.', '');
    }

    /**
     * @return list<Shop>
     */
    private function findActiveShops(): array
    {
        return $this->entityManager->createQuery('SELECT s FROM App\Entity\Shop s WHERE s.active = true ORDER BY s.name ASC')->getResult();
    }

    private function warmCaches(): void
    {
        foreach ($this->entityManager->getRepository(Brand::class)->findAll() as $brand) {
            $this->brandCache[$brand->getSlug()] = $brand;
        }

        foreach ($this->entityManager->getRepository(Category::class)->findAll() as $category) {
            $this->categoryCache[$category->getSlug()] = $category;
        }
    }

    private function cleanString(mixed $value, ?int $maxLength = null): ?string
    {
        $string = trim((string) ($value ?? ''));
        if ('' === $string) {
            return null;
        }

        $string = (string) iconv('UTF-8', 'UTF-8//IGNORE', $string);
        if ('' === $string) {
            return null;
        }

        return null === $maxLength ? $string : mb_substr($string, 0, $maxLength);
    }

    private function slugify(string $value, string $fallback): string
    {
        $slug = strtolower((string) $this->slugger->slug($value));

        return '' === $slug ? $fallback : mb_substr($slug, 0, 180);
    }
}
