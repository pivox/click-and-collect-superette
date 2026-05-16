<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function testAllStatusesHaveNonEmptyFrenchLabel(): void
    {
        foreach (OrderStatus::cases() as $status) {
            self::assertNotEmpty($status->labelFr(), "labelFr() is empty for {$status->value}");
        }
    }

    public function testAllStatusesHaveNonEmptyArabicLabel(): void
    {
        foreach (OrderStatus::cases() as $status) {
            self::assertNotEmpty($status->labelAr(), "labelAr() is empty for {$status->value}");
        }
    }

    public function testKnownLabels(): void
    {
        self::assertSame('Commande envoyée', OrderStatus::Submitted->labelFr());
        self::assertSame('تم إرسال الطلب', OrderStatus::Submitted->labelAr());
        self::assertSame('Retrait en cours', OrderStatus::PickupPending->labelFr());
        self::assertSame('الاستلام قيد التنفيذ', OrderStatus::PickupPending->labelAr());
        self::assertSame('Commande retirée', OrderStatus::Completed->labelFr());
        self::assertSame('تم استلام الطلب', OrderStatus::Completed->labelAr());
    }
}
