<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\ProductionDiagnosticsCommand;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class ProductionDiagnosticsCommandTest extends FunctionalApiTestCase
{
    public function testDiagnosticsCommandReportsHealthyRuntime(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:diagnostics:check');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);
        $display = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode, $display);
        self::assertStringContainsString('database', $display);
        self::assertStringContainsString('messenger_transport', $display);
        self::assertStringContainsString('environment', $display);
    }

    public function testDiagnosticsCommandFailsWhenJwtPublicKeyIsMissing(): void
    {
        $previousServerValue = $_SERVER['JWT_PUBLIC_KEY'] ?? null;
        $previousEnvValue = $_ENV['JWT_PUBLIC_KEY'] ?? null;
        $previousProcessValue = getenv('JWT_PUBLIC_KEY');
        unset($_SERVER['JWT_PUBLIC_KEY'], $_ENV['JWT_PUBLIC_KEY']);
        putenv('JWT_PUBLIC_KEY');

        try {
            $commandTester = $this->runContainerCommand();

            self::assertSame(Command::FAILURE, $commandTester->getStatusCode(), $commandTester->getDisplay());
            self::assertStringContainsString('JWT_PUBLIC_KEY', $commandTester->getDisplay());
        } finally {
            $this->restoreEnv('JWT_PUBLIC_KEY', $previousServerValue, $previousEnvValue, $previousProcessValue);
        }
    }

    public function testDiagnosticsCommandFailsForNonVerifiableAsyncTransportOutsideTestEnvironment(): void
    {
        $command = new ProductionDiagnosticsCommand(
            $this->entityManager,
            new class implements TransportInterface {
                public function get(): iterable
                {
                    return [];
                }

                public function ack(Envelope $envelope): void
                {
                }

                public function reject(Envelope $envelope): void
                {
                }

                public function send(Envelope $envelope): Envelope
                {
                    return $envelope;
                }
            },
        );
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode, $commandTester->getDisplay());
        self::assertStringContainsString('messenger_transport: error', $commandTester->getDisplay());
    }

    private function runContainerCommand(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:diagnostics:check');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        return $commandTester;
    }

    private function restoreEnv(string $name, ?string $serverValue, ?string $envValue, string|false $processValue): void
    {
        if (null === $serverValue) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $serverValue;
        }

        if (null === $envValue) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $envValue;
        }

        if (false === $processValue) {
            putenv($name);
        } else {
            putenv($name.'='.$processValue);
        }
    }
}
