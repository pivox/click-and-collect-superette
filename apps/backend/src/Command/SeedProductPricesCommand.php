<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\OpenDataProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:products:seed-prices',
    description: 'Assign random TND prices and activate imported OpenDataProduct entries (dev only)',
)]
final class SeedProductPricesCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('min', null, InputOption::VALUE_OPTIONAL, 'Minimum price in TND', '0.2')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'Maximum price in TND', '15.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding TND prices for imported products...');

        $min = (float) ($input->getOption('min') ?? 0.2);
        $max = (float) ($input->getOption('max') ?? 15.0);

        if ($min >= $max) {
            $io->error('--min must be strictly less than --max.');

            return Command::FAILURE;
        }

        $minCents = (int) round($min * 1000);
        $maxCents = (int) round($max * 1000);

        $processed = 0;

        do {
            /** @var list<OpenDataProduct> $batch */
            $batch = $this->entityManager
                ->createQuery('SELECT p FROM App\Entity\OpenDataProduct p WHERE p.priceTnd IS NULL ORDER BY p.id')
                ->setMaxResults(self::BATCH_SIZE)
                ->getResult();

            if ([] === $batch) {
                break;
            }

            foreach ($batch as $product) {
                $priceCents = random_int($minCents, $maxCents);
                $priceStr = number_format($priceCents / 1000, 3, '.', '');

                $product
                    ->setPriceTnd($priceStr)
                    ->setStock(random_int(5, 50))
                    ->setActive(true);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            $processed += \count($batch);
            $io->text(\sprintf('  Processed %d products...', $processed));
        } while (self::BATCH_SIZE === \count($batch));

        $io->success(\sprintf('Done. %d products updated with TND prices and activated.', $processed));

        return Command::SUCCESS;
    }
}
