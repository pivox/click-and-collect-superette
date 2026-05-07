<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use ApiPlatform\Metadata\Get;
use App\Entity\PlatformTheme;
use App\Entity\Shop;
use App\Entity\ShopTheme;
use App\Mapper\ShopThemeMapper;
use App\Provider\MerchantShopThemeProvider;
use App\Repository\PlatformThemeRepository;
use App\Repository\ShopRepository;
use App\Service\ThemeContrastChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MerchantShopThemeProviderTest extends TestCase
{
    public function testItProvidesOwnedShopTheme(): void
    {
        $shopTheme = (new ShopTheme())->setPrimaryColor('#123456');
        $shop = (new Shop())->setTheme($shopTheme);

        $provider = new MerchantShopThemeProvider(
            $this->shopRepositoryFinding($shop),
            $this->platformThemeRepositoryNotCalled(),
            $this->mapper(),
            $this->authorizationCheckerReturning(true),
        );

        $output = $provider->provide(new Get(), ['storeId' => (string) $shop->getId()]);

        self::assertSame('#123456', $output->primaryColor);
        self::assertSame((string) $shop->getId(), $output->storeId);
    }

    public function testItFallsBackToPlatformThemeWhenShopHasNoTheme(): void
    {
        $shop = new Shop();
        $platformTheme = (new PlatformTheme())->setPrimaryColor('#654321');

        $provider = new MerchantShopThemeProvider(
            $this->shopRepositoryFinding($shop),
            $this->platformThemeRepositoryFindingDefault($platformTheme),
            $this->mapper(),
            $this->authorizationCheckerReturning(true),
        );

        $output = $provider->provide(new Get(), ['storeId' => (string) $shop->getId()]);

        self::assertSame('#654321', $output->primaryColor);
        self::assertSame((string) $shop->getId(), $output->storeId);
    }

    public function testItDeniesNonOwner(): void
    {
        $shop = new Shop();

        $provider = new MerchantShopThemeProvider(
            $this->shopRepositoryFinding($shop),
            $this->platformThemeRepositoryNotCalled(),
            $this->mapper(),
            $this->authorizationCheckerReturning(false),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('SHOP_THEME_FORBIDDEN');

        $provider->provide(new Get(), ['storeId' => (string) $shop->getId()]);
    }

    public function testItRejectsUnknownStore(): void
    {
        $provider = new MerchantShopThemeProvider(
            $this->shopRepositoryFinding(null),
            $this->platformThemeRepositoryNotCalled(),
            $this->mapper(),
            $this->authorizationCheckerNotCalled(),
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('STORE_NOT_FOUND');

        $provider->provide(new Get(), ['storeId' => (string) (new Shop())->getId()]);
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

    /**
     * @return PlatformThemeRepository&MockObject
     */
    private function platformThemeRepositoryNotCalled(): PlatformThemeRepository
    {
        $repository = $this->createMock(PlatformThemeRepository::class);
        $repository
            ->expects(self::never())
            ->method('findDefault');

        return $repository;
    }

    private function mapper(): ShopThemeMapper
    {
        return new ShopThemeMapper(new ThemeContrastChecker());
    }

    /**
     * @return AuthorizationCheckerInterface&MockObject
     */
    private function authorizationCheckerReturning(bool $granted): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::once())
            ->method('isGranted')
            ->willReturn($granted);

        return $authorizationChecker;
    }

    /**
     * @return AuthorizationCheckerInterface&MockObject
     */
    private function authorizationCheckerNotCalled(): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::never())
            ->method('isGranted');

        return $authorizationChecker;
    }
}
