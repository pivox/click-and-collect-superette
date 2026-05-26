<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductImportRaw;
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
    name: 'app:product-import-raw:promote',
    description: 'Promote raw product import observations into pending product references',
)]
final class PromoteProductImportRawCommand extends Command
{
    private const int BATCH_SIZE = 200;

    /** @var array<string, Brand> */
    private array $brandCache = [];

    /** @var array<string, Category> */
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Display stats without writing product references')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of raw rows to process (0 = all)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');

        $io = new SymfonyStyle($input, $output);
        $io->title('Promote product_import_raw → product_references');

        $dryRun = (bool) $input->getOption('dry-run');
        $limit = max(0, (int) ($input->getOption('limit') ?? 0));

        if ($dryRun) {
            $io->note('Dry-run mode — no product reference will be written.');
        } else {
            $this->warmCaches();
        }

        $stats = ['processed' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];
        $total = $this->countPendingRawRows();
        if (0 < $limit) {
            $total = min($total, $limit);
        }

        $progressBar = new ProgressBar($output, $total);
        $progressBar->start();
        $offset = 0;

        while (true) {
            if (0 < $limit && $stats['processed'] >= $limit) {
                break;
            }

            $remaining = 0 < $limit ? min(self::BATCH_SIZE, $limit - $stats['processed']) : self::BATCH_SIZE;
            $batch = $this->fetchPendingRawRows($remaining, $dryRun ? $offset : 0);
            if ([] === $batch) {
                break;
            }

            foreach ($batch as $rawImport) {
                ++$stats['processed'];
                $progressBar->advance();

                try {
                    $created = $this->promoteOne($rawImport, $dryRun);
                    $created ? ++$stats['created'] : ++$stats['skipped'];
                } catch (\Throwable) {
                    ++$stats['errors'];
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            } else {
                $offset += \count($batch);
            }

            if (\count($batch) < $remaining) {
                break;
            }
        }

        $progressBar->finish();
        $output->writeln('');

        $io->success(\sprintf(
            'Promotion complete — processed: %d | created: %d | skipped: %d | errors: %d',
            $stats['processed'],
            $stats['created'],
            $stats['skipped'],
            $stats['errors'],
        ));

        return Command::SUCCESS;
    }

    private function promoteOne(ProductImportRaw $rawImport, bool $dryRun): bool
    {
        $nameFr = trim($rawImport->getRawTitle());
        if ('' === $nameFr) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $brand = $this->resolveBrand($rawImport->getRawBrand());
        $category = $this->resolveCategory($rawImport->getRawCategory());
        [$volume, $unit] = $this->parseQuantity($rawImport->getRawQuantity());

        $productReference = (new ProductReference())
            ->setNameFr(mb_substr($nameFr, 0, 255))
            ->setBrand($brand)
            ->setCategory($category)
            ->setVolume($volume)
            ->setUnit($unit)
            ->setStatus(ProductReferenceStatus::PendingReview)
            ->setSourceImportRaw($rawImport);

        $this->entityManager->persist($productReference);

        return true;
    }

    private function resolveBrand(?string $rawBrand): Brand
    {
        $name = $this->cleanFirst($rawBrand) ?? 'Générique';
        $slug = $this->slug($name);

        if (isset($this->brandCache[$slug])) {
            return $this->brandCache[$slug];
        }

        $existing = $this->entityManager->getRepository(Brand::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Brand) {
            $this->brandCache[$slug] = $existing;

            return $existing;
        }

        $brand = (new Brand())
            ->setCanonicalName(mb_substr($name, 0, 160))
            ->setSlug(mb_substr($slug, 0, 180))
            ->setActive(true);

        $this->entityManager->persist($brand);
        $this->brandCache[$slug] = $brand;

        return $brand;
    }

    private function resolveCategory(?string $rawCategory): Category
    {
        $name = $this->cleanFirst($rawCategory) ?? 'Divers';
        $slug = $this->slug($name);

        if (isset($this->categoryCache[$slug])) {
            return $this->categoryCache[$slug];
        }

        $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]);
        if ($existing instanceof Category) {
            $this->categoryCache[$slug] = $existing;

            return $existing;
        }

        $category = (new Category())
            ->setNameFr(mb_substr($name, 0, 160))
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
        if (null === $raw || '' === trim($raw)) {
            return [null, ProductUnit::Piece];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(ml|cl|dl|l|litre|litres|g|gr|gramme|grammes|kg|kilogramme|kilogrammes)$/i', trim($raw), $matches)) {
            $value = (float) str_replace(',', '.', $matches[1]);
            $rawUnit = strtolower($matches[2]);

            $unit = match (true) {
                \in_array($rawUnit, ['l', 'litre', 'litres'], true) => ProductUnit::Litre,
                \in_array($rawUnit, ['ml', 'cl', 'dl'], true) => ProductUnit::Millilitre,
                \in_array($rawUnit, ['kg', 'kilogramme', 'kilogrammes'], true) => ProductUnit::Kilogramme,
                \in_array($rawUnit, ['g', 'gr', 'gramme', 'grammes'], true) => ProductUnit::Gramme,
                default => ProductUnit::Piece,
            };

            if ('cl' === $rawUnit) {
                $value *= 10;
            }
            if ('dl' === $rawUnit) {
                $value *= 100;
            }

            return [$this->formatDecimal($value), $unit];
        }

        return [null, ProductUnit::Piece];
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function cleanFirst(?string $raw): ?string
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }

        $first = trim(explode(',', $raw)[0]);

        return '' === $first ? null : $first;
    }

    private function slug(string $value): string
    {
        return strtolower((string) $this->slugger->slug($value));
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

    private function countPendingRawRows(): int
    {
        return (int) $this->entityManager->createQuery(
            'SELECT COUNT(raw.id) FROM App\Entity\ProductImportRaw raw
             WHERE NOT EXISTS (
                 SELECT ref.id FROM App\Entity\ProductReference ref WHERE ref.sourceImportRaw = raw
             )'
        )->getSingleScalarResult();
    }

    /**
     * @return list<ProductImportRaw>
     */
    private function fetchPendingRawRows(int $limit, int $offset = 0): array
    {
        /* @var list<ProductImportRaw> */
        return $this->entityManager->createQuery(
            'SELECT raw FROM App\Entity\ProductImportRaw raw
             WHERE NOT EXISTS (
                 SELECT ref.id FROM App\Entity\ProductReference ref WHERE ref.sourceImportRaw = raw
             )
             ORDER BY raw.createdAt ASC, raw.id ASC'
        )
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getResult();
    }
}
