<?php

declare(strict_types=1);

namespace App\Billing;

final class PaymentReminderSchedule
{
    /**
     * @var array<int, PaymentReminderStage>
     */
    private const STAGES_BY_DAY_OFFSET = [
        -7 => PaymentReminderStage::BeforeDueDate,
        0 => PaymentReminderStage::DueDate,
        7 => PaymentReminderStage::GracePeriod7,
        14 => PaymentReminderStage::GracePeriod14,
        21 => PaymentReminderStage::SuspensionWarning21,
    ];

    public static function resolveStage(\DateTimeImmutable $dueDate, \DateTimeImmutable $referenceDate): ?PaymentReminderStage
    {
        $dueDay = $dueDate->setTime(0, 0);
        $referenceDay = $referenceDate->setTime(0, 0);
        $offset = (int) $dueDay->diff($referenceDay)->format('%r%a');

        return self::STAGES_BY_DAY_OFFSET[$offset] ?? null;
    }
}
