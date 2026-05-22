<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\ProductImportRaw;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteProductImportRawCommandTest extends FunctionalApiTestCase
{
    public function testPromotesRawImportIntoPendingProductReferenceWithSourceLink(): void
    {
        $raw = $this->createRawImport(
            sourceUrl: 'https://mg.tn/huile-olive-750ml',
            rawTitle: "Huile d'olive extra vierge",
            rawBrand: 'Zitouna',
            rawQuantity: '750 ml',
            rawCategory: 'Épicerie',
        );

        $commandTester = $this->runCommand();

        /** @var ProductReference|null $productReference */
        $productReference = $this->entityManager->getRepository(ProductReference::class)->findOneBy(['nameFr' => "Huile d'olive extra vierge"]);
        self::assertInstanceOf(ProductReference::class, $productReference);
        self::assertSame(ProductReferenceStatus::PendingReview, $productReference->getStatus());
        self::assertSame('Zitouna', $productReference->getBrand()->getCanonicalName());
        self::assertSame('Épicerie', $productReference->getCategory()->getNameFr());
        self::assertSame('750', $productReference->getVolume());
        self::assertSame(ProductUnit::Millilitre, $productReference->getUnit());
        self::assertSame($raw->getId()->toRfc4122(), $productReference->getSourceImportRaw()?->getId()->toRfc4122());
        self::assertStringContainsString('created: 1', $commandTester->getDisplay());
    }

    public function testPromoteCommandCanRunTwiceWithoutDuplicatingImportedRows(): void
    {
        $this->createRawImport(
            sourceUrl: 'https://mg.tn/lait-1l',
            rawTitle: 'Lait demi-écrémé',
            rawBrand: 'Vitalait',
            rawQuantity: '1 l',
            rawCategory: 'Lait',
        );

        $this->runCommand();
        $this->runCommand();

        self::assertSame(1, $this->entityManager->getRepository(ProductReference::class)->count([]));
    }

    public function testDryRunDoesNotCreateProductReference(): void
    {
        $this->createRawImport(
            sourceUrl: 'https://mg.tn/cafe',
            rawTitle: 'Café moulu',
            rawBrand: 'Bondin',
            rawQuantity: '250 g',
            rawCategory: 'Épicerie',
        );

        $commandTester = $this->runCommand(['--dry-run' => true]);

        self::assertSame(0, $this->entityManager->getRepository(ProductReference::class)->count([]));
        self::assertStringContainsString('Dry-run mode', $commandTester->getDisplay());
        self::assertStringContainsString('created: 1', $commandTester->getDisplay());
    }

    public function testDryRunUsesNextBatchInsteadOfReReadingFirstRows(): void
    {
        for ($i = 1; $i <= 200; ++$i) {
            $this->createRawImport(
                sourceUrl: \sprintf('https://mg.tn/valid-%03d', $i),
                rawTitle: 'Produit valide '.$i,
            );
        }

        $this->createRawImport(
            sourceUrl: 'https://mg.tn/invalid-empty-title',
            rawTitle: '',
        );

        $commandTester = $this->runCommand(['--dry-run' => true, '--limit' => 201]);

        self::assertStringContainsString('processed: 201', $commandTester->getDisplay());
        self::assertStringContainsString('created: 200', $commandTester->getDisplay());
        self::assertStringContainsString('skipped: 1', $commandTester->getDisplay());
        self::assertSame(0, $this->entityManager->getRepository(ProductReference::class)->count([]));
    }

    public function testPromoteConvertsCentilitresToMillilitres(): void
    {
        $this->createRawImport(
            sourceUrl: 'https://mg.tn/jus-75cl',
            rawTitle: 'Jus orange',
            rawQuantity: '75 cl',
        );

        $this->runCommand();

        /** @var ProductReference|null $productReference */
        $productReference = $this->entityManager->getRepository(ProductReference::class)->findOneBy(['nameFr' => 'Jus orange']);
        self::assertInstanceOf(ProductReference::class, $productReference);
        self::assertSame('750', $productReference->getVolume());
        self::assertSame(ProductUnit::Millilitre, $productReference->getUnit());
    }

    public function testPromoteConvertsDecilitresToMillilitres(): void
    {
        $this->createRawImport(
            sourceUrl: 'https://mg.tn/boisson-2dl',
            rawTitle: 'Boisson canette',
            rawQuantity: '2 dl',
        );

        $this->runCommand();

        /** @var ProductReference|null $productReference */
        $productReference = $this->entityManager->getRepository(ProductReference::class)->findOneBy(['nameFr' => 'Boisson canette']);
        self::assertInstanceOf(ProductReference::class, $productReference);
        self::assertSame('200', $productReference->getVolume());
        self::assertSame(ProductUnit::Millilitre, $productReference->getUnit());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input = []): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:product-import-raw:promote');
        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute($input);

        self::assertSame(0, $exitCode, $commandTester->getDisplay());

        return $commandTester;
    }

    private function createRawImport(
        string $sourceUrl,
        string $rawTitle,
        ?string $rawBrand = null,
        ?string $rawQuantity = null,
        ?string $rawCategory = null,
    ): ProductImportRaw {
        $raw = (new ProductImportRaw())
            ->setSourceName('mg.tn')
            ->setSourceUrl($sourceUrl)
            ->setRawTitle($rawTitle)
            ->setRawBrand($rawBrand)
            ->setRawQuantity($rawQuantity)
            ->setRawCategory($rawCategory)
            ->setRawPayload([
                'title' => $rawTitle,
                'url' => $sourceUrl,
            ])
            ->setProductionUsable(false);

        $this->entityManager->persist($raw);
        $this->entityManager->flush();

        return $raw;
    }
}
