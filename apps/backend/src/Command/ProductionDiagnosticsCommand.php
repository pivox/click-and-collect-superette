<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[AsCommand(
    name: 'app:diagnostics:check',
    description: 'Checks minimal production readiness for database, Messenger and critical environment variables.',
)]
final class ProductionDiagnosticsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'messenger.transport.async')]
        private readonly TransportInterface $asyncTransport,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hasFailure = false;

        $io->title('Production diagnostics');

        if (!$this->checkDatabase($io)) {
            $hasFailure = true;
        }
        if (!$this->checkMessengerTransport($io)) {
            $hasFailure = true;
        }
        if (!$this->checkCriticalEnvironment($io)) {
            $hasFailure = true;
        }

        if ($hasFailure) {
            $io->error('Diagnostics completed with anomalies.');

            return Command::FAILURE;
        }

        $io->success('Diagnostics completed successfully.');

        return Command::SUCCESS;
    }

    private function checkDatabase(SymfonyStyle $io): bool
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1')->fetchOne();
            $io->writeln('database: ok');

            return true;
        } catch (\Throwable $exception) {
            $io->writeln('database: error');
            $io->writeln('database_error: '.$exception::class);

            return false;
        }
    }

    private function checkMessengerTransport(SymfonyStyle $io): bool
    {
        try {
            if ($this->asyncTransport instanceof MessageCountAwareInterface) {
                $this->asyncTransport->getMessageCount();
            }

            $io->writeln('messenger_transport: ok');

            return true;
        } catch (\Throwable $exception) {
            $io->writeln('messenger_transport: error');
            $io->writeln('messenger_transport_error: '.$exception::class);

            return false;
        }
    }

    private function checkCriticalEnvironment(SymfonyStyle $io): bool
    {
        $missing = [];
        foreach (['APP_SECRET', 'DATABASE_URL', 'JWT_SECRET_KEY'] as $name) {
            if ('' === trim($this->getEnvValue($name))) {
                $missing[] = $name;
            }
        }

        if ([] !== $missing) {
            $io->writeln('environment: error');
            $io->writeln('missing_environment: '.implode(', ', $missing));

            return false;
        }

        $io->writeln('environment: ok');

        return true;
    }

    private function getEnvValue(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) ? $value : '';
    }
}
