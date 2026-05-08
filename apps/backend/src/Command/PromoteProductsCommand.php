<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\OpenDataProduct;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(
    name: 'app:products:promote',
    description: 'Promote open_data_products into brands, categories and product_references',
)]
final class PromoteProductsCommand extends Command
{
    private const BATCH_SIZE = 200;

    /** @var array<string, Brand> slug → managed entity */
    private array $brandCache = [];

    /** @var array<string, Category> slug → managed entity */
    private array $categoryCache = [];

    private AsciiSlugger $slugger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
        $this->slugger = new AsciiSlugger('fr');
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and display stats without writing to the database')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of products to process (0 = all)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $io->title('Promote open_data_products → brands / categories / product_references');

        $dryRun = (bool) $input->getOption('dry-run');
        $limit = max(0, (int) ($input->getOption('limit') ?? 0));

        if ($dryRun) {
            $io->note('Dry-run mode — no data will be written to the database.');
        }

        if (!$dryRun) {
            $this->warmCaches();
        }

        $stats = ['processed' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];

        $total = $this->countPending();
        if (0 < $limit) {
            $total = min($total, $limit);
        }

        $progressBar = new ProgressBar($output, $total);
        $progressBar->start();

        $batchCount = 0;

        while (true) {
            if (0 < $limit && $stats['processed'] >= $limit) {
                break;
            }

            $remaining = 0 < $limit ? min(self::BATCH_SIZE, $limit - $stats['processed']) : self::BATCH_SIZE;

            // Always fetch from offset 0: flushed products are excluded by NOT EXISTS
            $batch = $this->fetchBatch($remaining);
            if ([] === $batch) {
                break;
            }

            foreach ($batch as $odp) {
                ++$stats['processed'];
                $progressBar->advance();

                try {
                    $created = $this->promoteOne($odp, $dryRun);
                    $created ? ++$stats['created'] : ++$stats['skipped'];
                } catch (\Throwable) {
                    ++$stats['errors'];
                }

                ++$batchCount;
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }

            gc_collect_cycles();

            if (\count($batch) < $remaining) {
                break;
            }
        }

        $progressBar->finish();
        $output->writeln('');

        $io->success(\sprintf(
            'Promotion complete — processed: %d | created: %d | skipped: %d | errors: %d',
            $stats['processed'], $stats['created'], $stats['skipped'], $stats['errors'],
        ));

        return Command::SUCCESS;
    }

    private function promoteOne(OpenDataProduct $odp, bool $dryRun): bool
    {
        $nameFr = $odp->getNameFr() ?? $odp->getName();
        if (null === $nameFr || '' === $nameFr) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $brand = $this->resolveBrand($odp->getBrand());
        $category = $this->resolveCategory($odp->getCategoryFr() ?? $odp->getCategory());

        [$volume, $unit] = $this->parseQuantity($odp->getQuantity());

        $ref = new ProductReference();
        $ref->setBarcode($odp->getBarcode())
            ->setNameFr(mb_substr($nameFr, 0, 255))
            ->setNameAr($odp->getNameAr() !== null ? mb_substr($odp->getNameAr(), 0, 255) : null)
            ->setBrand($brand)
            ->setCategory($category)
            ->setVolume($volume)
            ->setUnit($unit)
            ->setStatus(ProductReferenceStatus::PendingReview);

        $this->entityManager->persist($ref);

        return true;
    }

    private function resolveBrand(?string $rawBrand): Brand
    {
        $name = $this->cleanFirst($rawBrand) ?? 'Générique';
        $slug = strtolower((string) $this->slugger->slug($name));

        if (isset($this->brandCache[$slug])) {
            return $this->brandCache[$slug];
        }

        $existing = $this->entityManager->getRepository(Brand::class)->findOneBy(['slug' => $slug]);
        if (null !== $existing) {
            $this->brandCache[$slug] = $existing;

            return $existing;
        }

        $brand = new Brand();
        $brand->setCanonicalName(mb_substr($name, 0, 160))
            ->setSlug(mb_substr($slug, 0, 180));

        $this->entityManager->persist($brand);
        $this->brandCache[$slug] = $brand;

        return $brand;
    }

    private function resolveCategory(?string $rawCategory): Category
    {
        $name = $this->cleanFirst($rawCategory) ?? 'Divers';
        $slug = strtolower((string) $this->slugger->slug($name));

        if (isset($this->categoryCache[$slug])) {
            return $this->categoryCache[$slug];
        }

        $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]);
        if (null !== $existing) {
            $this->categoryCache[$slug] = $existing;

            return $existing;
        }

        $category = new Category();
        $category->setNameFr(mb_substr($name, 0, 160))
            ->setSlug(mb_substr($slug, 0, 180))
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->categoryCache[$slug] = $category;

        return $category;
    }

    /**
     * @return array{?string, ProductUnit}
     */
    private function parseQuantity(?string $raw): array
    {
        if (null === $raw || '' === $raw) {
            return [null, ProductUnit::Piece];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(ml|cl|dl|l|litre|litres|g|gr|gramme|grammes|kg|kilogramme|kilogrammes)$/i', trim($raw), $m)) {
            $value = str_replace(',', '.', $m[1]);
            $rawUnit = strtolower($m[2]);

            $unit = match (true) {
                \in_array($rawUnit, ['l', 'litre', 'litres'], true) => ProductUnit::Litre,
                \in_array($rawUnit, ['ml', 'cl', 'dl'], true) => ProductUnit::Millilitre,
                \in_array($rawUnit, ['kg', 'kilogramme', 'kilogrammes'], true) => ProductUnit::Kilogramme,
                \in_array($rawUnit, ['g', 'gr', 'gramme', 'grammes'], true) => ProductUnit::Gramme,
                default => ProductUnit::Piece,
            };

            return [$value, $unit];
        }

        return [null, ProductUnit::Piece];
    }

    private function cleanFirst(?string $raw): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        // Take the first comma-separated value and strip "lang:" prefixes (e.g. "en:beverages")
        $first = trim(explode(',', $raw)[0]);
        $first = trim((string) preg_replace('/^[a-z]{2}:/i', '', $first));

        return '' === $first ? null : $first;
    }

    private function warmCaches(): void
    {
        /** @var Brand[] $brands */
        $brands = $this->entityManager->getRepository(Brand::class)->findAll();
        foreach ($brands as $brand) {
            $this->brandCache[$brand->getSlug()] = $brand;
        }

        /** @var Category[] $categories */
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        foreach ($categories as $category) {
            $this->categoryCache[$category->getSlug()] = $category;
        }
    }

    private function countPending(): int
    {
        return (int) $this->entityManager->createQuery(
            'SELECT COUNT(o.id) FROM App\Entity\OpenDataProduct o
             WHERE NOT EXISTS (
                 SELECT r.id FROM App\Entity\ProductReference r WHERE r.barcode = o.barcode
             )'
        )->getSingleScalarResult();
    }

    /** @return list<OpenDataProduct> */
    private function fetchBatch(int $limit): array
    {
        /** @var list<OpenDataProduct> */
        return $this->entityManager->createQuery(
            'SELECT o FROM App\Entity\OpenDataProduct o
             WHERE NOT EXISTS (
                 SELECT r.id FROM App\Entity\ProductReference r WHERE r.barcode = o.barcode
             )
             ORDER BY o.id ASC'
        )
            ->setMaxResults($limit)
            ->getResult();
    }
}
