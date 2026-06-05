<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SubscriptionPricingPhaseRefresher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:subscriptions:refresh-pricing-phases',
    description: 'Refreshes merchant subscription pricing phases when trial or promo periods end.',
)]
final class RefreshSubscriptionPricingPhasesCommand extends Command
{
    public function __construct(
        private readonly SubscriptionPricingPhaseRefresher $refresher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'now',
            null,
            InputOption::VALUE_REQUIRED,
            'Reference date as an ISO-8601 datetime. Defaults to the current time.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $now = $this->parseNow($input->getOption('now'));
        } catch (\Exception) {
            $output->writeln('invalid_now');

            return Command::FAILURE;
        }

        $refreshed = $this->refresher->refreshDuePricingPhases($now);

        $output->writeln(\sprintf('refreshed_subscription_pricing_phases: %d', $refreshed));
        $this->logger->info('subscription.pricing_phases_refreshed', ['count' => $refreshed]);

        return Command::SUCCESS;
    }

    private function parseNow(mixed $rawNow): \DateTimeImmutable
    {
        if (null === $rawNow || '' === $rawNow) {
            return new \DateTimeImmutable();
        }

        return new \DateTimeImmutable((string) $rawNow);
    }
}
