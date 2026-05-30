<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PickupSlotDisplayTime;
use PHPUnit\Framework\TestCase;

final class PickupSlotDisplayTimeTest extends TestCase
{
    public function testItPreservesGeneratedLocalClockForDisplay(): void
    {
        $storedLocalClock = new \DateTimeImmutable('2026-05-28T10:00:00+00:00');

        self::assertSame('2026-05-28T10:00:00+01:00', PickupSlotDisplayTime::toLocalAtom($storedLocalClock));
    }

    public function testItNormalizesManualUtcPayloadToTunisiaLocalClock(): void
    {
        $payloadInstant = new \DateTimeImmutable('2026-05-28T16:00:00+00:00');
        $normalized = PickupSlotDisplayTime::fromPayloadInstant($payloadInstant);

        self::assertSame('2026-05-28 17:00:00', $normalized->format('Y-m-d H:i:s'));
        self::assertSame('Africa/Tunis', $normalized->getTimezone()->getName());
        self::assertSame('2026-05-28T17:00:00+01:00', PickupSlotDisplayTime::toLocalAtom($normalized));
    }
}
