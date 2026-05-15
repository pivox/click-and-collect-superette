<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ImportProductsCommand;
use App\Entity\OpenDataProduct;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ImportProductsCommandTest extends TestCase
{
    public function testMapFieldsKeepsExistingProductActivationOnRefresh(): void
    {
        $command = new ImportProductsCommand(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
        );
        $product = (new OpenDataProduct())
            ->setBarcode('6190000000001')
            ->setName('Old name')
            ->setSource('off')
            ->setType('food')
            ->setPriceTnd('1.500')
            ->setStock(12)
            ->setActive(true);

        $this->invokeMapFields($command, $product, [
            'code' => '6190000000001',
            'product_name' => 'New name',
            'product_name_fr' => 'Nouveau nom',
        ], false);

        self::assertTrue($product->isActive());
        self::assertSame('1.500', $product->getPriceTnd());
        self::assertSame(12, $product->getStock());
        self::assertSame('New name', $product->getName());
        self::assertSame('Nouveau nom', $product->getNameFr());
    }

    public function testMapFieldsInitializesNewProductsAsInactive(): void
    {
        $command = new ImportProductsCommand(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
        );
        $product = new OpenDataProduct();

        $this->invokeMapFields($command, $product, [
            'code' => '6190000000002',
            'product_name' => 'New product',
        ], true);

        self::assertFalse($product->isActive());
        self::assertSame('6190000000002', $product->getBarcode());
        self::assertSame('New product', $product->getName());
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function invokeMapFields(ImportProductsCommand $command, OpenDataProduct $product, array $raw, bool $isNew): void
    {
        $method = new \ReflectionMethod(ImportProductsCommand::class, 'mapFields');
        $method->setAccessible(true);
        $method->invoke($command, $product, $raw, 'off', 'food', $isNew);
    }
}
