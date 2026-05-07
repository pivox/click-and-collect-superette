<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\PlatformTheme;
use App\Entity\Shop;
use App\Entity\ShopTheme;
use App\Exception\StoreDisabledException;
use App\Exception\StoreNotFoundException;
use App\Repository\PlatformThemeRepository;
use App\Repository\ShopRepository;
use App\Service\ThemeResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ThemeResolverTest extends TestCase
{
    public function testItResolvesShopThemeBeforePlatformTheme(): void
    {
        $shopTheme = new ShopTheme();
        $shop = (new Shop())
            ->setName('Supérette Test')
            ->setSlug('superette-test')
            ->setQrCodeToken('qr-test')
            ->setTheme($shopTheme);

        $shopRepository = $this->shopRepositoryFinding($shop);
        $platformThemeRepository = $this->createMock(PlatformThemeRepository::class);
        $platformThemeRepository
            ->expects(self::never())
            ->method('findDefault');

        $resolvedTheme = (new ThemeResolver($shopRepository, $platformThemeRepository))
            ->resolveForStore((string) $shop->getId());

        self::assertSame($shopTheme, $resolvedTheme);
    }

    public function testItFallsBackToPlatformTheme(): void
    {
        $shop = (new Shop())
            ->setName('Supérette Test')
            ->setSlug('superette-test')
            ->setQrCodeToken('qr-test');
        $platformTheme = new PlatformTheme();

        $platformThemeRepository = $this->platformThemeRepositoryFindingDefault($platformTheme);

        $resolvedTheme = (new ThemeResolver($this->shopRepositoryFinding($shop), $platformThemeRepository))
            ->resolveForStore((string) $shop->getId());

        self::assertSame($platformTheme, $resolvedTheme);
    }

    public function testItRejectsUnknownStore(): void
    {
        $this->expectException(StoreNotFoundException::class);
        $this->expectExceptionMessage('STORE_NOT_FOUND');

        $platformThemeRepository = $this->createMock(PlatformThemeRepository::class);
        $platformThemeRepository
            ->expects(self::never())
            ->method('findDefault');

        (new ThemeResolver($this->shopRepositoryFinding(null), $platformThemeRepository))
            ->resolveForStore((string) Uuid::v4());
    }

    public function testItRejectsDisabledStore(): void
    {
        $shop = (new Shop())
            ->setName('Supérette Test')
            ->setSlug('superette-test')
            ->setQrCodeToken('qr-test')
            ->setActive(false);

        $this->expectException(StoreDisabledException::class);
        $this->expectExceptionMessage('STORE_DISABLED');

        $platformThemeRepository = $this->createMock(PlatformThemeRepository::class);
        $platformThemeRepository
            ->expects(self::never())
            ->method('findDefault');

        (new ThemeResolver($this->shopRepositoryFinding($shop), $platformThemeRepository))
            ->resolveForStore((string) $shop->getId());
    }

    /**
     * @return ShopRepository&MockObject
     */
    private function shopRepositoryFinding(?Shop $shop): ShopRepository
    {
        $repository = $this->createMock(ShopRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->willReturn($shop);

        return $repository;
    }

    /**
     * @return PlatformThemeRepository&MockObject
     */
    private function platformThemeRepositoryFindingDefault(PlatformTheme $platformTheme): PlatformThemeRepository
    {
        $repository = $this->createMock(PlatformThemeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findDefault')
            ->willReturn($platformTheme);

        return $repository;
    }
}
