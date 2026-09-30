<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RefreshTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purges refresh tokens expired or revoked for more than N days (#616).
 *
 * Recommended cron (daily):
 *   0 4 * * * php bin/console app:auth:purge-refresh-tokens
 */
#[AsCommand(
    name: 'app:auth:purge-refresh-tokens',
    description: 'Deletes refresh tokens expired or revoked for more than the retention period (default 30 days).',
)]
final class PurgeRefreshTokensCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'retention-days',
            null,
            InputOption::VALUE_REQUIRED,
            'Number of days an expired or revoked token is kept before deletion (audit window).',
            (string) self::DEFAULT_RETENTION_DAYS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retentionDays = (int) $input->getOption('retention-days');
        if ($retentionDays < 0) {
            $io->error('retention-days must be >= 0.');

            return Command::INVALID;
        }

        $cutoff = (new \DateTimeImmutable())->modify(\sprintf('-%d days', $retentionDays));
        $deleted = $this->refreshTokenRepository->purgeObsolete($cutoff);

        $io->success(\sprintf(
            '%d refresh token(s) expired or revoked before %s deleted.',
            $deleted,
            $cutoff->format(\DateTimeInterface::ATOM),
        ));

        return Command::SUCCESS;
    }
}
