<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\PaymentReminderSchedule;
use App\Billing\PaymentReminderStage;
use PHPUnit\Framework\TestCase;

final class PaymentReminderScheduleTest extends TestCase
{
    public function testResolvesDocumentedReminderStages(): void
    {
        $dueDate = new \DateTimeImmutable('2026-06-11 09:00:00');

        self::assertSame(
            PaymentReminderStage::BeforeDueDate,
            PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-06-04 10:00:00')),
        );
        self::assertSame(
            PaymentReminderStage::DueDate,
            PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-06-11 08:00:00')),
        );
        self::assertSame(
            PaymentReminderStage::GracePeriod7,
            PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-06-18 12:00:00')),
        );
        self::assertSame(
            PaymentReminderStage::GracePeriod14,
            PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-06-25 12:00:00')),
        );
        self::assertSame(
            PaymentReminderStage::SuspensionWarning21,
            PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-07-02 12:00:00')),
        );
    }

    public function testIgnoresDaysOutsideMvpSchedule(): void
    {
        $dueDate = new \DateTimeImmutable('2026-06-11 09:00:00');

        self::assertNull(PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-06-08 09:00:00')));
        self::assertNull(PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-06-14 09:00:00')));
        self::assertNull(PaymentReminderSchedule::resolveStage($dueDate, new \DateTimeImmutable('2026-07-03 09:00:00')));
    }
}
