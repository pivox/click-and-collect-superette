<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\OpenDataProduct;
use App\Repository\OpenDataProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:products:import',
    description: 'Import products from Open Food Facts, Open Beauty Facts and Open Products Facts',
)]
final class ImportProductsCommand extends Command
{
    private const BATCH_SIZE = 200;
    private const RATE_LIMIT_US = 100_000;

    private const OFF_TUNISIA_URL = 'https://world.openfoodfacts.org/cgi/search.pl?action=process&tagtype_0=countries&tag_contains_0=contains&tag_0=tunisia&page_size=1000&json=1&page={page}';
    private const OFF_WORLD_URL = 'https://world.openfoodfacts.org/cgi/search.pl?action=process&sort_by=unique_scans_n&page_size=1000&json=1&page={page}';

    /**
     * @var array<string, array{type: string, url: string}>
     */
    private const SOURCES = [
        'off' => ['type' => 'food',    'url' => self::OFF_TUNISIA_URL],
        'obf' => ['type' => 'beauty',  'url' => 'https://world.openbeautyfacts.org/cgi/search.pl?action=process&sort_by=unique_scans_n&page_size=1000&json=1&page={page}'],
        'opf' => ['type' => 'product', 'url' => 'https://world.openproductsfacts.org/cgi/search.pl?action=process&sort_by=unique_scans_n&page_size=1000&json=1&page={page}'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'monolog.logger.catalog')]
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Source: off|obf|opf|all', 'all')
            ->addOption('pages', null, InputOption::VALUE_OPTIONAL, 'Number of pages per source (1 page ≈ 1000 products)', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and display stats without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $io->title('Open*Facts product import');

        $sourceArg = $input->getOption('source') ?? 'all';
        $maxPages = max(1, (int) ($input->getOption('pages') ?? 30));
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry-run mode — no data will be written to the database.');
        }

        if ('all' !== $sourceArg && !isset(self::SOURCES[$sourceArg])) {
            $io->error(\sprintf('Unknown source "%s". Valid values: off, obf, opf, all.', $sourceArg));

            return Command::FAILURE;
        }

        /** @var OpenDataProductRepository $repository */
        $repository = $this->entityManager->getRepository(OpenDataProduct::class);

        $sourceKeys = ('all' === $sourceArg) ? array_keys(self::SOURCES) : [$sourceArg];

        $grand = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($sourceKeys as $key) {
            $io->section(\sprintf('Source: %s (%s)', $key, self::SOURCES[$key]['type']));

            $stats = $this->importSource($key, $maxPages, $dryRun, $repository, $output);

            foreach (array_keys($grand) as $stat) {
                $grand[$stat] += $stats[$stat];
            }

            $io->text(\sprintf(
                '  → fetched %d | inserted %d | updated %d | skipped %d | errors %d',
                $stats['fetched'], $stats['inserted'], $stats['updated'], $stats['skipped'], $stats['errors']
            ));
        }

        $io->success(\sprintf(
            'Import complete — fetched: %d | inserted: %d | updated: %d | skipped: %d | errors: %d',
            $grand['fetched'], $grand['inserted'], $grand['updated'], $grand['skipped'], $grand['errors']
        ));

        $this->logger->info('catalog.import.done', [
            'sources' => $sourceKeys,
            'fetched' => $grand['fetched'],
            'inserted' => $grand['inserted'],
            'updated' => $grand['updated'],
            'skipped' => $grand['skipped'],
            'errors' => $grand['errors'],
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return array{fetched: int, inserted: int, updated: int, skipped: int, errors: int}
     */
    private function importSource(
        string $sourceKey,
        int $maxPages,
        bool $dryRun,
        OpenDataProductRepository $repository,
        OutputInterface $output,
    ): array {
        $config = self::SOURCES[$sourceKey];
        $type = $config['type'];

        /** @var array{fetched: int, inserted: int, updated: int, skipped: int, errors: int} */
        $stats = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $baseUrl = $config['url'];

        $page1Data = $this->fetchPage($baseUrl, 1);
        if (null === $page1Data) {
            ++$stats['errors'];

            return $stats;
        }

        // Fallback: too few Tunisia products → switch to world URL
        if ('off' === $sourceKey && \count($page1Data['products'] ?? []) < 100) {
            $baseUrl = self::OFF_WORLD_URL;
            $page1Data = $this->fetchPage($baseUrl, 1);
            if (null === $page1Data) {
                ++$stats['errors'];

                return $stats;
            }
        }

        $pageCount = max(1, (int) ($page1Data['page_count'] ?? 1));
        $pagesToFetch = min($maxPages, $pageCount);
        $totalProducts = min((int) ($page1Data['count'] ?? $pagesToFetch * 1000), $pagesToFetch * 1000);

        $progressBar = new ProgressBar($output, $totalProducts);
        $progressBar->setFormat('  %current%/%max% [%bar%] %percent:3s%% — page %message%');
        $progressBar->start();

        $batchCount = 0;
        $gcCount = 0;

        for ($page = 1; $page <= $pagesToFetch; ++$page) {
            if ($page > 1) {
                usleep(self::RATE_LIMIT_US);
            }

            $progressBar->setMessage((string) $page);
            $pageData = (1 === $page) ? $page1Data : $this->fetchPage($baseUrl, $page);
            unset($page1Data);

            if (null === $pageData) {
                ++$stats['errors'];
                continue;
            }

            foreach ($pageData['products'] ?? [] as $raw) {
                ++$stats['fetched'];
                $progressBar->advance();

                $barcode = substr(trim((string) ($raw['code'] ?? '')), 0, 30);
                if ('' === $barcode) {
                    ++$stats['skipped'];
                    $this->logger->debug('catalog.import.skipped', ['reason' => 'empty_barcode', 'source' => $sourceKey]);
                    continue;
                }

                $name = trim((string) ($raw['product_name'] ?? ''));
                $nameFr = trim((string) ($raw['product_name_fr'] ?? ''));
                if ('' === $name && '' === $nameFr) {
                    ++$stats['skipped'];
                    $this->logger->debug('catalog.import.skipped', ['reason' => 'no_name', 'barcode' => $barcode, 'source' => $sourceKey]);
                    continue;
                }

                if ($dryRun) {
                    // Skip DB access entirely in dry-run — all valid products counted as fetched
                    ++$stats['inserted'];
                    continue;
                }

                $existing = $repository->findOneByBarcode($barcode);
                $isNew = null === $existing;
                $product = $existing ?? new OpenDataProduct();

                $this->mapFields($product, $raw, $sourceKey, $type, $isNew);
                $this->entityManager->persist($product);

                $isNew ? ++$stats['inserted'] : ++$stats['updated'];

                ++$batchCount;
                if (0 === ($batchCount % self::BATCH_SIZE)) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }

            unset($pageData);
            gc_collect_cycles();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $progressBar->finish();
        $output->writeln('');

        return $stats;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $baseUrl, int $page): ?array
    {
        $url = str_replace('{page}', (string) $page, $baseUrl);

        $this->logger->debug('catalog.import.fetch', ['url' => $url]);

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 30]);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('catalog.import.fetch_failed', [
                'url' => $url,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('catalog.import.fetch_failed', [
                'url' => $url,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function mapFields(OpenDataProduct $product, array $raw, string $source, string $type, bool $isNew): void
    {
        /** @var array<string, mixed> $nutriments */
        $nutriments = $raw['nutriments'] ?? [];

        $nutritionMap = [
            'energy' => $nutriments['energy-kcal_100g'] ?? null,
            'proteins' => $nutriments['proteins_100g'] ?? null,
            'carbs' => $nutriments['carbohydrates_100g'] ?? null,
            'fat' => $nutriments['fat_100g'] ?? null,
            'fiber' => $nutriments['fiber_100g'] ?? null,
            'salt' => $nutriments['salt_100g'] ?? null,
        ];

        $nutrition = [];
        foreach ($nutritionMap as $k => $v) {
            if (null !== $v && '' !== (string) $v) {
                $nutrition[$k] = (float) $v;
            }
        }

        $nutriscore = strtoupper(substr(trim((string) ($raw['nutriscore_grade'] ?? '')), 0, 1));
        $ecoscore = strtoupper(substr(trim((string) ($raw['ecoscore_grade'] ?? '')), 0, 1));

        $product
            ->setBarcode(substr(trim((string) ($raw['code'] ?? '')), 0, 30))
            ->setName($this->str($raw['product_name'] ?? null, 255))
            ->setNameFr($this->str($raw['product_name_fr'] ?? null, 255))
            ->setNameAr($this->str($raw['product_name_ar'] ?? null, 255))
            ->setBrand($this->str($raw['brands'] ?? null, 255))
            ->setCategory($this->str($raw['categories'] ?? null, 255))
            ->setCategoryFr($this->str($raw['categories_fr'] ?? null, 255))
            ->setQuantity($this->str($raw['quantity'] ?? null, 100))
            ->setImageUrl($this->str($raw['image_url'] ?? null, 500))
            ->setImageThumbUrl($this->str($raw['image_thumb_url'] ?? null, 500))
            ->setIngredients($this->str($raw['ingredients_text_fr'] ?? null))
            ->setAllergens($this->str($raw['allergens_fr'] ?? null, 500))
            ->setNutriscore('' !== $nutriscore ? $nutriscore : null)
            ->setEcoscore('' !== $ecoscore ? $ecoscore : null)
            ->setNutrition(empty($nutrition) ? null : $nutrition)
            ->setSource($source)
            ->setType($type);

        if ($isNew) {
            $product->setActive(false);
        }
    }

    private function str(mixed $value, ?int $maxLen = null): ?string
    {
        $str = trim((string) ($value ?? ''));

        if ('' === $str) {
            return null;
        }

        // Remove invalid UTF-8 byte sequences that PostgreSQL would reject
        $str = (string) iconv('UTF-8', 'UTF-8//IGNORE', $str);

        if ('' === $str) {
            return null;
        }

        // mb_substr preserves whole multi-byte characters unlike substr
        return (null !== $maxLen) ? mb_substr($str, 0, $maxLen) : $str;
    }
}
