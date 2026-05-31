<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\MerchantProductPriceHistory;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\MerchantProductPriceChangeType;
use App\Enum\MerchantProductPriceSource;
use App\Service\MerchantProductPriceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MerchantProductPriceServiceTest extends TestCase
{
    public function testRecordsInitialPriceHistory(): void
    {
        $merchant = $this->createUser();
        $merchantProduct = $this->createMerchantProduct($merchant, '1.500');
        $changedAt = new \DateTimeImmutable('2026-05-31T10:30:00+01:00');
        $persistedHistory = null;
        $service = new MerchantProductPriceService($this->entityManagerExpectingPersist($persistedHistory));

        $history = $service->recordInitialPrice(
            merchantProduct: $merchantProduct,
            source: MerchantProductPriceSource::MerchantDashboard,
            changedByUser: $merchant,
            changedAt: $changedAt,
        );

        self::assertSame($history, $persistedHistory);
        self::assertNull($history->getOldPrice());
        self::assertSame('1.500', $history->getNewPrice());
        self::assertSame('TND', $history->getCurrency());
        self::assertSame(MerchantProductPriceChangeType::Initial, $history->getChangeType());
        self::assertSame(MerchantProductPriceSource::MerchantDashboard, $history->getSource());
        self::assertSame($merchant, $history->getMerchant());
        self::assertSame($merchant, $history->getChangedByUser());
        self::assertSame($changedAt, $history->getChangedAt());
    }

    public function testChangesPriceAndRecordsOldAndNewValues(): void
    {
        $merchant = $this->createUser();
        $merchantProduct = $this->createMerchantProduct($merchant, '2.500');
        $persistedHistory = null;
        $service = new MerchantProductPriceService($this->entityManagerExpectingPersist($persistedHistory));

        $history = $service->changePrice(
            merchantProduct: $merchantProduct,
            newPrice: '2.700',
            changeType: MerchantProductPriceChangeType::ManualUpdate,
            source: MerchantProductPriceSource::MerchantDashboard,
            changedByUser: $merchant,
            reason: ' Ajustement fournisseur ',
        );

        self::assertSame($history, $persistedHistory);
        self::assertSame('2.700', $merchantProduct->getPriceTnd());
        self::assertSame('2.500', $history?->getOldPrice());
        self::assertSame('2.700', $history?->getNewPrice());
        self::assertSame(MerchantProductPriceChangeType::ManualUpdate, $history?->getChangeType());
        self::assertSame('Ajustement fournisseur', $history?->getReason());
    }

    public function testDoesNotRecordHistoryWhenPriceIsUnchanged(): void
    {
        $merchantProduct = $this->createMerchantProduct($this->createUser(), '2.500');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $service = new MerchantProductPriceService($entityManager);

        $history = $service->changePrice(
            merchantProduct: $merchantProduct,
            newPrice: '2.500',
            changeType: MerchantProductPriceChangeType::ManualUpdate,
            source: MerchantProductPriceSource::MerchantDashboard,
        );

        self::assertNull($history);
        self::assertSame('2.500', $merchantProduct->getPriceTnd());
    }

    public function testRejectsNegativePrice(): void
    {
        $merchantProduct = $this->createMerchantProduct($this->createUser(), '2.500');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $service = new MerchantProductPriceService($entityManager);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PRICE_MUST_BE_POSITIVE');

        $service->changePrice(
            merchantProduct: $merchantProduct,
            newPrice: '-1.000',
            changeType: MerchantProductPriceChangeType::ManualUpdate,
            source: MerchantProductPriceSource::MerchantDashboard,
        );
    }

    public function testRejectsEmptyCurrencyWithoutChangingCurrentPrice(): void
    {
        $merchantProduct = $this->createMerchantProduct($this->createUser(), '2.500');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $service = new MerchantProductPriceService($entityManager);

        try {
            $service->changePrice(
                merchantProduct: $merchantProduct,
                newPrice: '2.700',
                changeType: MerchantProductPriceChangeType::ManualUpdate,
                source: MerchantProductPriceSource::MerchantDashboard,
                currency: ' ',
            );
            self::fail('Expected empty currency to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('CURRENCY_MUST_NOT_BE_EMPTY', $exception->getMessage());
            self::assertSame('2.500', $merchantProduct->getPriceTnd());
        }
    }

    private function entityManagerExpectingPersist(?MerchantProductPriceHistory &$persistedHistory): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (MerchantProductPriceHistory $history) use (&$persistedHistory): bool {
                $persistedHistory = $history;

                return true;
            }));

        return $entityManager;
    }

    private function createMerchantProduct(User $merchant, string $priceTnd): MerchantProduct
    {
        $shop = (new Shop())
            ->setName('Supérette Test')
            ->setSlug('superette-test')
            ->setQrCodeToken('qr-test')
            ->setOwner($merchant);

        $brand = (new Brand())
            ->setCanonicalName('Vitalait')
            ->setSlug('vitalait');

        $category = (new Category())
            ->setNameFr('Lait')
            ->setSlug('lait');

        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Lait demi-écrémé');

        return (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($priceTnd);
    }

    private function createUser(): User
    {
        return (new User())
            ->setEmail('merchant@example.test')
            ->setPassword('test-password')
            ->setName('Marchand Test')
            ->setRoles(['ROLE_MERCHANT']);
    }
}
