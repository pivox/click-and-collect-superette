<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\ProductReference;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedProductReferencesCommandTest extends FunctionalApiTestCase
{
    public function testSeedProductReferencesCanRunTwiceWithoutDuplicatingProducts(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:seed:product-references');
        $commandTester = new CommandTester($command);

        $firstExitCode = $commandTester->execute([]);
        $firstCount = $this->entityManager->getRepository(ProductReference::class)->count([]);

        $secondExitCode = $commandTester->execute([]);
        $secondCount = $this->entityManager->getRepository(ProductReference::class)->count([]);

        self::assertSame(0, $firstExitCode);
        self::assertSame(0, $secondExitCode);
        self::assertSame(17, $firstCount);
        self::assertSame($firstCount, $secondCount);
        self::assertStringContainsString('0 brands, 0 categories, 0 products created', $commandTester->getDisplay());
    }
}
