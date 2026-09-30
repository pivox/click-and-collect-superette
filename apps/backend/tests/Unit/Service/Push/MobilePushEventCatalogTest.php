<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Push;

use App\Enum\MobileApplication;
use App\Service\NotificationService;
use App\Service\Push\MobilePushEventCatalog;
use PHPUnit\Framework\TestCase;

final class MobilePushEventCatalogTest extends TestCase
{
    public function testV1ClientTypesAreNormalizedToThemselves(): void
    {
        foreach ([
            NotificationService::TYPE_ORDER_ACCEPTED,
            NotificationService::TYPE_ORDER_PARTIALLY_ACCEPTED,
            NotificationService::TYPE_ORDER_REJECTED,
            NotificationService::TYPE_ORDER_READY,
            NotificationService::TYPE_ORDER_COMPLETED,
            NotificationService::TYPE_PICKUP_REMINDER,
            NotificationService::TYPE_MERCHANT_RESPONSE_TIMEOUT,
            NotificationService::TYPE_PARTIAL_ACCEPTANCE_REMINDER,
            NotificationService::TYPE_PARTIAL_ACCEPTANCE_TIMEOUT,
            NotificationService::TYPE_MERCHANT_ORDER_SUBMITTED,
            NotificationService::TYPE_MERCHANT_ORDER_CANCELLED,
        ] as $type) {
            self::assertSame($type, MobilePushEventCatalog::normalize($type));
        }
    }

    public function testNonV1TypesEmitNothing(): void
    {
        self::assertNull(MobilePushEventCatalog::normalize(null));
        self::assertNull(MobilePushEventCatalog::normalize(NotificationService::TYPE_ORDER_PREPARING));
        self::assertNull(MobilePushEventCatalog::normalize(NotificationService::TYPE_MERCHANT_PICKUP_COMPLETED));
        self::assertNull(MobilePushEventCatalog::normalize('order_preparing_550e8400-e29b-41d4-a716-446655440000'));
        self::assertNull(MobilePushEventCatalog::normalize('unknown_type'));
    }

    public function testPerCycleVariantsAreNormalizedToTheirBaseType(): void
    {
        self::assertSame(
            NotificationService::TYPE_PARTIAL_ACCEPTANCE_REMINDER,
            MobilePushEventCatalog::normalize('partial_acceptance_reminder_550e8400-e29b-41d4-a716-446655440000'),
        );
        self::assertSame(
            NotificationService::TYPE_ORDER_PARTIALLY_ACCEPTED,
            MobilePushEventCatalog::normalize('order_partially_accepted_550e8400-e29b-41d4-a716-446655440000'),
        );
    }

    public function testApplicationAndRouteMapping(): void
    {
        self::assertSame(MobileApplication::Client, MobilePushEventCatalog::applicationFor(NotificationService::TYPE_ORDER_READY));
        self::assertSame(MobileApplication::Merchant, MobilePushEventCatalog::applicationFor(NotificationService::TYPE_MERCHANT_ORDER_SUBMITTED));
        self::assertSame('/orders/abc', MobilePushEventCatalog::routeFor(NotificationService::TYPE_ORDER_READY, 'abc'));
        self::assertSame('/merchant/orders/abc', MobilePushEventCatalog::routeFor(NotificationService::TYPE_MERCHANT_ORDER_SUBMITTED, 'abc'));
    }
}
