<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MerchantOrganizationBackfiller;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:merchant-organizations:backfill',
    description: 'Creates one organization and one active membership per historical merchant account, and attaches its shops. Idempotent.',
)]
final class MerchantOrganizationsBackfillCommand extends Command
{
    public function __construct(
        private readonly MerchantOrganizationBackfiller $backfiller,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->backfiller->backfill();

        foreach ($report->counters() as $name => $value) {
            $output->writeln(\sprintf('%s: %d', $name, $value));
        }
        foreach ($report->orphanShopIds as $shopId) {
            $output->writeln(\sprintf('orphan_shop: %s', $shopId));
        }

        $this->logger->info('merchant_organizations.backfill_completed', $report->counters());

        return Command::SUCCESS;
    }
}
