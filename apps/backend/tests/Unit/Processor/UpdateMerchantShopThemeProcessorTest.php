<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use ApiPlatform\Metadata\Put;
use App\Dto\ThemeWriteInput;
use App\Entity\Shop;
use App\Entity\ShopTheme;
use App\Mapper\ShopThemeMapper;
use App\Processor\UpdateMerchantShopThemeProcessor;
use App\Repository\ShopRepository;
use App\Service\ThemeContrastChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class UpdateMerchantShopThemeProcessorTest extends TestCase
{
    public function testItCreatesShopThemeForOwnedShopWithoutTheme(): void
    {
        $shop = new Shop();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(ShopTheme::class));
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $processor = new UpdateMerchantShopThemeProcessor(
            $this->shopRepositoryFinding($shop),
            $this->mapper(),
            $entityManager,
            $this->authorizationCheckerReturning(true),
        );

        $output = $processor->process($this->input(), new Put(), ['storeId' => (string) $shop->getId()]);

        self::assertInstanceOf(ShopTheme::class, $shop->getTheme());
        self::assertSame('#123456', $shop->getTheme()->getPrimaryColor());
        self::assertSame('cairo', $output->fontFamily);
        self::assertSame((string) $shop->getId(), $output->storeId);
    }

    public function testItUpdatesExistingShopThemeForOwnedShop(): void
    {
        $shopTheme = new ShopTheme();
        $shop = (new Shop())->setTheme($shopTheme);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::never())
            ->method('persist');
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $processor = new UpdateMerchantShopThemeProcessor(
            $this->shopRepositoryFinding($shop),
            $this->mapper(),
            $entityManager,
            $this->authorizationCheckerReturning(true),
        );

        $processor->process($this->input(), new Put(), ['storeId' => (string) $shop->getId()]);

        self::assertSame($shopTheme, $shop->getTheme());
        self::assertSame('#123456', $shopTheme->getPrimaryColor());
        self::assertSame(18, $shopTheme->getBaseFontSize());
    }

    public function testItDeniesNonOwnerBeforeUpdating(): void
    {
        $shop = new Shop();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::never())
            ->method('persist');
        $entityManager
            ->expects(self::never())
            ->method('flush');

        $processor = new UpdateMerchantShopThemeProcessor(
            $this->shopRepositoryFinding($shop),
            $this->mapper(),
            $entityManager,
            $this->authorizationCheckerReturning(false),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('SHOP_THEME_FORBIDDEN');

        $processor->process($this->input(), new Put(), ['storeId' => (string) $shop->getId()]);
    }

    private function input(): ThemeWriteInput
    {
        return new ThemeWriteInput(
            primaryColor: '#123456',
            secondaryColor: '#234567',
            accentColor: '#345678',
            textColor: '#456789',
            backgroundColor: '#56789A',
            fontFamily: 'cairo',
            baseFontSize: 18,
        );
    }

    /**
     * @return ShopRepository&MockObject
     */
    private function shopRepositoryFinding(Shop $shop): ShopRepository
    {
        $repository = $this->createMock(ShopRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->willReturn($shop);

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
}
