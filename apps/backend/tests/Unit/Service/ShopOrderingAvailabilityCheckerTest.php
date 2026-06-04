<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Shop;
use App\Entity\User;
use App\Service\ShopOrderingAvailabilityChecker;
use PHPUnit\Framework\TestCase;

final class ShopOrderingAvailabilityCheckerTest extends TestCase
{
    public function testActiveShopWithActiveMerchantAcceptsNewKadhias(): void
    {
        $merchant = (new User())->setActive(true);
        $shop = (new Shop())->setActive(true)->setOwner($merchant);

        self::assertTrue((new ShopOrderingAvailabilityChecker())->acceptsNewKadhias($shop));
        self::assertNull((new ShopOrderingAvailabilityChecker())->blockReason($shop));
    }

    public function testSuspendedMerchantBlocksNewKadhiasWithoutArchivingShop(): void
    {
        $merchant = (new User())->setActive(false);
        $shop = (new Shop())->setActive(true)->setOwner($merchant);

        self::assertFalse((new ShopOrderingAvailabilityChecker())->acceptsNewKadhias($shop));
        self::assertSame('STORE_SUSPENDED_FOR_SUBSCRIPTION', (new ShopOrderingAvailabilityChecker())->blockReason($shop));
        self::assertNull($shop->getArchivedAt());
        self::assertTrue($shop->isActive());
    }
}
