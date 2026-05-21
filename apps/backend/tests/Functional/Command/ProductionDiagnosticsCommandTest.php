<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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
}
