<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:dev:seed-demo-store',
    description: 'Seed a demo store with a merchant-owned catalog for local client journey testing',
)]
final class SeedDemoStoreCommand extends Command
{
    private const DEMO_MERCHANT_EMAIL = 'merchant.demo@kadhia.local';
    private const DEMO_MERCHANT_PASSWORD = 'password';
    private const DEMO_MERCHANT_NAME = 'Marchand Demo';
    private const DEMO_STORE_NAME = 'Supérette El Amen';
    private const DEMO_STORE_SLUG = 'superette-el-amen';
    private const DEMO_STORE_CITY = 'Tunis';
    private const DEMO_STORE_COUNTRY = 'TN';
    private const DEMO_STORE_QR_CODE_TOKEN = 'demo-superette-el-amen';

    /** @var array<int, string> */
    private const DEMO_PRICE_BY_BARCODE = [
        '6191234560001' => '1.750',
        '6191234560002' => '1.650',
        '6191234560003' => '1.800',
        '6191234560010' => '0.550',
        '6191234560011' => '0.650',
        '6191234560020' => '3.200',
        '6191234560030' => '0.900',
        '6191234560031' => '0.500',
        '6191234560040' => '3.200',
        '6191234560041' => '3.100',
        '6191234560050' => '4.800',
        '6191234560051' => '18.500',
        '6191234560060' => '5.500',
        '6191234560061' => '2.400',
        '6191234560070' => '1.400',
        '6191234560071' => '1.600',
        '6191234560080' => '1.200',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'catalog',
            null,
            InputOption::VALUE_REQUIRED,
            'Catalog mode: demo for a small curated catalog, all for every approved product reference',
            'demo',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $catalogMode = (string) $input->getOption('catalog');

        if (!\in_array($catalogMode, ['demo', 'all'], true)) {
            $io->error('Invalid catalog mode. Allowed values: demo, all.');

            return Command::FAILURE;
        }

        $io->title('Seeding demo store...');

        $merchant = $this->createOrUpdateDemoMerchant();
        $shop = $this->createOrUpdateDemoStore($merchant);
        $productReferences = $this->selectProductReferences($catalogMode);

        if ([] === $productReferences) {
            $io->error('No approved product reference found. Run app:seed:product-references or import/approve products first.');

            return Command::FAILURE;
        }

        $result = $this->upsertMerchantCatalog($shop, $productReferences);
        $this->entityManager->flush();

        $visibleCatalogCount = $this->entityManager->getRepository(MerchantProduct::class)->count([
            'shop' => $shop,
            'isVisible' => true,
        ]);

        $io->success('Demo store ready.');
        $io->definitionList(
            ['merchant_email' => self::DEMO_MERCHANT_EMAIL],
            ['merchant_password' => self::DEMO_MERCHANT_PASSWORD],
            ['store_id' => $shop->getId()->toRfc4122()],
            ['store_slug' => $shop->getSlug()],
            ['qr_code_token' => $shop->getQrCodeToken()],
            ['catalog_url' => \sprintf('/api/stores/%s/catalog', $shop->getId()->toRfc4122())],
            ['catalog_mode' => $catalogMode],
            ['products_added' => (string) $result['added']],
            ['products_updated' => (string) $result['updated']],
            ['visible_catalog_total' => (string) $visibleCatalogCount],
        );

        return Command::SUCCESS;
    }

    private function createOrUpdateDemoMerchant(): User
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $merchant = $userRepository->findOneBy(['email' => self::DEMO_MERCHANT_EMAIL]);

        if (!$merchant instanceof User) {
            $merchant = new User();
            $merchant->setEmail(self::DEMO_MERCHANT_EMAIL);
            $this->entityManager->persist($merchant);
        }

        $roles = $merchant->getRoles();
        $roles[] = 'ROLE_MERCHANT';

        $merchant
            ->setName(self::DEMO_MERCHANT_NAME)
            ->setRoles(array_values(array_unique($roles)))
            ->setPassword($this->passwordHasher->hashPassword($merchant, self::DEMO_MERCHANT_PASSWORD))
            ->setActive(true);

        return $merchant;
    }

    private function createOrUpdateDemoStore(User $merchant): Shop
    {
        $shopRepository = $this->entityManager->getRepository(Shop::class);
        $shop = $shopRepository->findOneBy(['slug' => self::DEMO_STORE_SLUG]);

        if (!$shop instanceof Shop) {
            $shop = $shopRepository->findOneBy(['qrCodeToken' => self::DEMO_STORE_QR_CODE_TOKEN]);
        }

        if (!$shop instanceof Shop) {
            $shop = new Shop();
            $this->entityManager->persist($shop);
        }

        $shop
            ->setName(self::DEMO_STORE_NAME)
            ->setSlug(self::DEMO_STORE_SLUG)
            ->setCity(self::DEMO_STORE_CITY)
            ->setCountry(self::DEMO_STORE_COUNTRY)
            ->setActive(true)
            ->setQrCodeToken(self::DEMO_STORE_QR_CODE_TOKEN)
            ->setOwner($merchant);

        return $shop;
    }

    /**
     * @return list<ProductReference>
     */
    private function selectProductReferences(string $catalogMode): array
    {
        $approvedReferences = $this->entityManager->getRepository(ProductReference::class)->findBy(
            ['status' => ProductReferenceStatus::Approved],
            ['nameFr' => 'ASC'],
        );

        if ('all' === $catalogMode) {
            /* @var list<ProductReference> $approvedReferences */
            return $approvedReferences;
        }

        $demoReferences = [];
        foreach ($approvedReferences as $productReference) {
            $barcode = $productReference->getBarcode();
            if (null !== $barcode && ctype_digit($barcode) && \array_key_exists((int) $barcode, self::DEMO_PRICE_BY_BARCODE)) {
                $demoReferences[] = $productReference;
            }
        }

        if ([] !== $demoReferences) {
            return $demoReferences;
        }

        /* @var list<ProductReference> */
        return \array_slice($approvedReferences, 0, 15);
    }

    /**
     * @param list<ProductReference> $productReferences
     *
     * @return array{added: int, updated: int}
     */
    private function upsertMerchantCatalog(Shop $shop, array $productReferences): array
    {
        $merchantProductRepository = $this->entityManager->getRepository(MerchantProduct::class);
        $added = 0;
        $updated = 0;

        foreach ($productReferences as $productReference) {
            $merchantProduct = $merchantProductRepository->findOneBy([
                'shop' => $shop,
                'productReference' => $productReference,
            ]);

            if (!$merchantProduct instanceof MerchantProduct) {
                $merchantProduct = (new MerchantProduct())
                    ->setShop($shop)
                    ->setProductReference($productReference);
                $this->entityManager->persist($merchantProduct);
                ++$added;
            } else {
                ++$updated;
            }

            $merchantProduct
                ->setPriceTnd($this->resolvePriceTnd($productReference))
                ->setVisible(true)
                ->setAvailable(true);
        }

        return ['added' => $added, 'updated' => $updated];
    }

    private function resolvePriceTnd(ProductReference $productReference): string
    {
        $barcode = $productReference->getBarcode();
        if (null !== $barcode && ctype_digit($barcode)) {
            $barcodeKey = (int) $barcode;
            if (isset(self::DEMO_PRICE_BY_BARCODE[$barcodeKey])) {
                return self::DEMO_PRICE_BY_BARCODE[$barcodeKey];
            }
        }

        [$minimumMillimes, $maximumMillimes] = $this->priceRangeForUnit($productReference->getUnit());
        $source = $barcode ?? $productReference->getId()->toRfc4122();
        $hash = (int) \sprintf('%u', crc32($source));
        $millimes = $minimumMillimes + ($hash % ($maximumMillimes - $minimumMillimes + 1));

        return number_format($millimes / 1000, 3, '.', '');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function priceRangeForUnit(ProductUnit $unit): array
    {
        return match ($unit) {
            ProductUnit::Litre => [1000, 8000],
            ProductUnit::Millilitre => [500, 5000],
            ProductUnit::Kilogramme => [1000, 12000],
            ProductUnit::Gramme => [500, 8000],
            ProductUnit::Piece => [300, 5000],
            ProductUnit::Paquet => [1000, 15000],
        };
    }
}
