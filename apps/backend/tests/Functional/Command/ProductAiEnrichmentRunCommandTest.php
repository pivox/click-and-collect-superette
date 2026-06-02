<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ProductAiEnrichmentRunCommandTest extends FunctionalApiTestCase
{
    public function testRunPlansJobsAndSkipsOpenAiWhenApiKeyIsMissing(): void
    {
        unset($_ENV['OPENAI_API_KEY'], $_SERVER['OPENAI_API_KEY']);
        $this->createIncompleteProductReference();

        $application = new Application(self::$kernel);
        $command = $application->find('app:products:ai-enrichment:run');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--limit' => '5', '--batch-size' => '5']);

        self::assertSame(0, $exitCode, $commandTester->getDisplay());
        self::assertSame(1, $this->entityManager->getRepository(ProductAiEnrichmentJob::class)->count([]));
        self::assertStringContainsString('OPENAI_API_KEY is missing', $commandTester->getDisplay());
        self::assertMatchesRegularExpression('/jobs_created\s+1/', $commandTester->getDisplay());
        self::assertMatchesRegularExpression('/jobs_submitted\s+0/', $commandTester->getDisplay());
    }

    private function createIncompleteProductReference(): void
    {
        $brand = (new Brand())
            ->setCanonicalName('Marque non vérifiée')
            ->setSlug('marque-non-verifiee');
        $category = (new Category())
            ->setNameFr('Boissons')
            ->setSlug('boissons');
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Eau minerale 1.5 l')
            ->setVolume('1.500')
            ->setUnit(ProductUnit::Litre)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();
    }
}
