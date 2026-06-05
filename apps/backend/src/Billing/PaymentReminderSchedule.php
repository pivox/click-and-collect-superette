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
        -3 => PaymentReminderStage::BeforeDueDate3,
        0 => PaymentReminderStage::DueDate,
        3 => PaymentReminderStage::GracePeriod3,
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

    /**
     * @return list<array{stage: PaymentReminderStage, scheduled_at: \DateTimeImmutable}>
     */
    public static function stagesForDueDate(\DateTimeImmutable $dueDate): array
    {
        $dueAt = $dueDate->setTime(
            (int) $dueDate->format('H'),
            (int) $dueDate->format('i'),
            (int) $dueDate->format('s'),
        );

        return array_map(
            static fn (PaymentReminderStage $stage): array => [
                'stage' => $stage,
                'scheduled_at' => $dueAt->modify(\sprintf('%+d days', $stage->dayOffset())),
            ],
            [
                PaymentReminderStage::BeforeDueDate,
                PaymentReminderStage::BeforeDueDate3,
                PaymentReminderStage::DueDate,
                PaymentReminderStage::GracePeriod3,
                PaymentReminderStage::GracePeriod7,
                PaymentReminderStage::GracePeriod14,
                PaymentReminderStage::SuspensionWarning21,
            ],
        );
    }
}
