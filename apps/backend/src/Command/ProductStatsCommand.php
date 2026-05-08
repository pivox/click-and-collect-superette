<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:products:stats',
    description: 'Display statistics about imported OpenDataProduct entries',
)]
final class ProductStatsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Open data product statistics');

        $sources = [
            'off' => 'off (food)',
            'obf' => 'obf (beauty)',
            'opf' => 'opf (other)',
        ];

        $rows = [];
        $grandTotal = 0;
        $grandImage = 0;
        $grandPrice = 0;
        $grandActive = 0;

        foreach ($sources as $key => $label) {
            $total = $this->dqlCount('p.source = :src', ['src' => $key]);
            $withImage = $this->dqlCount('p.source = :src AND p.imageUrl IS NOT NULL', ['src' => $key]);
            $withPrice = $this->dqlCount('p.source = :src AND p.priceTnd IS NOT NULL', ['src' => $key]);
            $active = $this->dqlCount('p.source = :src AND p.active = true', ['src' => $key]);

            $rows[] = [$label, $total, $withImage, $withPrice, $active];

            $grandTotal += $total;
            $grandImage += $withImage;
            $grandPrice += $withPrice;
            $grandActive += $active;
        }

        $rows[] = ['TOTAL', $grandTotal, $grandImage, $grandPrice, $grandActive];

        $io->table(
            ['Source', 'Total', 'Avec image', 'Avec prix', 'Actifs'],
            $rows
        );

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function dqlCount(string $condition, array $params = []): int
    {
        $dql = sprintf('SELECT COUNT(p.id) FROM App\Entity\OpenDataProduct p WHERE %s', $condition);
        $query = $this->entityManager->createQuery($dql);

        foreach ($params as $name => $value) {
            $query->setParameter($name, $value);
        }

        return (int) $query->getSingleScalarResult();
    }
}
