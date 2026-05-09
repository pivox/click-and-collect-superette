<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:product-references',
    description: 'Seed the product reference with initial Tunisian products',
)]
final class SeedProductReferencesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Drop and recreate all brands, categories and product references before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding product references...');

        if ($input->getOption('reset')) {
            foreach ($this->entityManager->getRepository(ProductReference::class)->findAll() as $ref) {
                $this->entityManager->remove($ref);
            }
            foreach ($this->entityManager->getRepository(Brand::class)->findAll() as $brand) {
                $this->entityManager->remove($brand);
            }
            foreach ($this->entityManager->getRepository(Category::class)->findAll() as $category) {
                $this->entityManager->remove($category);
            }
            $this->entityManager->flush();
        }

        $brandsData = [
            ['canonicalName' => 'Vitalait', 'slug' => 'vitalait'],
            ['canonicalName' => 'Délice', 'slug' => 'delice'],
            ['canonicalName' => 'Slama', 'slug' => 'slama'],
            ['canonicalName' => 'Poulina', 'slug' => 'poulina'],
            ['canonicalName' => 'Centrale Laitière', 'slug' => 'centrale-laitiere'],
            ['canonicalName' => 'Unilever Tunisia', 'slug' => 'unilever-tunisia'],
            ['canonicalName' => 'Nestlé Tunisia', 'slug' => 'nestle-tunisia'],
            ['canonicalName' => 'Ben Jemaa', 'slug' => 'ben-jemaa'],
        ];

        $brandMap = [];
        $brandsCreated = 0;
        foreach ($brandsData as $data) {
            $existing = $this->entityManager->getRepository(Brand::class)->findOneBy(['slug' => $data['slug']]);
            if (null !== $existing) {
                $brandMap[$data['slug']] = $existing;
                continue;
            }

            $brand = (new Brand())
                ->setCanonicalName($data['canonicalName'])
                ->setSlug($data['slug']);
            $this->entityManager->persist($brand);
            $brandMap[$data['slug']] = $brand;
            ++$brandsCreated;
        }

        $rootCategoriesData = [
            ['nameFr' => 'Produits laitiers', 'slug' => 'produits-laitiers', 'sortOrder' => 1],
            ['nameFr' => 'Boissons', 'slug' => 'boissons', 'sortOrder' => 2],
            ['nameFr' => 'Épicerie', 'slug' => 'epicerie', 'sortOrder' => 3],
            ['nameFr' => 'Hygiène', 'slug' => 'hygiene', 'sortOrder' => 4],
        ];

        $subCategoriesData = [
            ['nameFr' => 'Lait', 'slug' => 'lait', 'parent' => 'produits-laitiers', 'sortOrder' => 1],
            ['nameFr' => 'Yaourts', 'slug' => 'yaourts', 'parent' => 'produits-laitiers', 'sortOrder' => 2],
            ['nameFr' => 'Fromages', 'slug' => 'fromages', 'parent' => 'produits-laitiers', 'sortOrder' => 3],
            ['nameFr' => 'Eaux', 'slug' => 'eaux', 'parent' => 'boissons', 'sortOrder' => 1],
            ['nameFr' => 'Jus', 'slug' => 'jus', 'parent' => 'boissons', 'sortOrder' => 2],
            ['nameFr' => 'Huiles', 'slug' => 'huiles', 'parent' => 'epicerie', 'sortOrder' => 1],
            ['nameFr' => 'Conserves', 'slug' => 'conserves', 'parent' => 'epicerie', 'sortOrder' => 2],
            ['nameFr' => 'Farine & Semoule', 'slug' => 'farine-semoule', 'parent' => 'epicerie', 'sortOrder' => 3],
        ];

        $categoryMap = [];
        $categoriesCreated = 0;

        foreach ($rootCategoriesData as $data) {
            $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $data['slug']]);
            if (null !== $existing) {
                $categoryMap[$data['slug']] = $existing;
                continue;
            }

            $category = (new Category())
                ->setNameFr($data['nameFr'])
                ->setSlug($data['slug'])
                ->setSortOrder($data['sortOrder']);
            $this->entityManager->persist($category);
            $categoryMap[$data['slug']] = $category;
            ++$categoriesCreated;
        }

        // Flush root categories first so parent references resolve correctly
        $this->entityManager->flush();

        foreach ($subCategoriesData as $data) {
            $existing = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $data['slug']]);
            if (null !== $existing) {
                $categoryMap[$data['slug']] = $existing;
                continue;
            }

            $parent = $categoryMap[$data['parent']];

            $category = (new Category())
                ->setNameFr($data['nameFr'])
                ->setSlug($data['slug'])
                ->setSortOrder($data['sortOrder'])
                ->setParent($parent);
            $this->entityManager->persist($category);
            $categoryMap[$data['slug']] = $category;
            ++$categoriesCreated;
        }

        $this->entityManager->flush();

        $productsData = [
            ['nameFr' => 'Lait entier UHT', 'brand' => 'vitalait', 'category' => 'lait', 'volume' => '1.000', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560001'],
            ['nameFr' => 'Lait demi-écrémé UHT', 'brand' => 'vitalait', 'category' => 'lait', 'volume' => '1.000', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560002'],
            ['nameFr' => 'Lait entier frais', 'brand' => 'delice', 'category' => 'lait', 'volume' => '1.000', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560003'],
            ['nameFr' => 'Yaourt nature', 'brand' => 'delice', 'category' => 'yaourts', 'volume' => '0.125', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560010'],
            ['nameFr' => 'Yaourt aux fruits fraise', 'brand' => 'delice', 'category' => 'yaourts', 'volume' => '0.125', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560011'],
            ['nameFr' => 'Fromage fondu Vache Qui Rit', 'brand' => 'ben-jemaa', 'category' => 'fromages', 'volume' => '0.140', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560020'],
            ['nameFr' => 'Eau minérale Safia', 'brand' => 'slama', 'category' => 'eaux', 'volume' => '1.500', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560030'],
            ['nameFr' => 'Eau minérale Safia', 'brand' => 'slama', 'category' => 'eaux', 'volume' => '0.500', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560031'],
            ['nameFr' => "Jus d'orange", 'brand' => 'poulina', 'category' => 'jus', 'volume' => '1.000', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560040'],
            ['nameFr' => 'Jus de pomme', 'brand' => 'poulina', 'category' => 'jus', 'volume' => '1.000', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560041'],
            ['nameFr' => 'Huile végétale', 'brand' => 'centrale-laitiere', 'category' => 'huiles', 'volume' => '1.000', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560050'],
            ['nameFr' => "Huile d'olive extra vierge", 'brand' => 'slama', 'category' => 'huiles', 'volume' => '0.750', 'unit' => ProductUnit::Litre, 'barcode' => '6191234560051'],
            ['nameFr' => "Thon à l'huile", 'brand' => 'ben-jemaa', 'category' => 'conserves', 'volume' => '0.160', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560060'],
            ['nameFr' => 'Sardines à la tomate', 'brand' => 'ben-jemaa', 'category' => 'conserves', 'volume' => '0.125', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560061'],
            ['nameFr' => 'Farine de blé', 'brand' => 'poulina', 'category' => 'farine-semoule', 'volume' => '1.000', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560070'],
            ['nameFr' => 'Semoule fine', 'brand' => 'slama', 'category' => 'farine-semoule', 'volume' => '1.000', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560071'],
            ['nameFr' => 'Savon de Marseille', 'brand' => 'unilever-tunisia', 'category' => 'hygiene', 'volume' => '0.100', 'unit' => ProductUnit::Kilogramme, 'barcode' => '6191234560080'],
        ];

        $productsCreated = 0;
        foreach ($productsData as $data) {
            $existing = $this->entityManager->getRepository(ProductReference::class)->findOneBy(['barcode' => $data['barcode']]);
            if (null !== $existing) {
                continue;
            }

            $brand = $brandMap[$data['brand']];
            $category = $categoryMap[$data['category']];

            $ref = (new ProductReference())
                ->setNameFr($data['nameFr'])
                ->setBrand($brand)
                ->setCategory($category)
                ->setVolume($data['volume'])
                ->setUnit($data['unit'])
                ->setBarcode($data['barcode'])
                ->setStatus(ProductReferenceStatus::Approved);

            $this->entityManager->persist($ref);
            ++$productsCreated;
        }

        $this->entityManager->flush();

        $io->success(\sprintf('Done. %d brands, %d categories, %d products created.', $brandsCreated, $categoriesCreated, $productsCreated));

        return Command::SUCCESS;
    }
}
