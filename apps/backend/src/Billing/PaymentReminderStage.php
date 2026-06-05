<?php

declare(strict_types=1);

namespace App\Billing;

enum PaymentReminderStage: string
{
    case BeforeDueDate = 'j_minus_7';
    case BeforeDueDate3 = 'j_minus_3';
    case DueDate = 'j';
    case GracePeriod3 = 'j_plus_3';
    case GracePeriod7 = 'j_plus_7';
    case GracePeriod14 = 'j_plus_14';
    case SuspensionWarning21 = 'j_plus_21';

    public function labelFr(): string
    {
        return match ($this) {
            self::BeforeDueDate => 'J-7',
            self::BeforeDueDate3 => 'J-3',
            self::DueDate => 'J0',
            self::GracePeriod3 => 'J+3',
            self::GracePeriod7 => 'J+7',
            self::GracePeriod14 => 'J+14',
            self::SuspensionWarning21 => 'J+21',
        };
    }

    public function dayOffset(): int
    {
        return match ($this) {
            self::BeforeDueDate => -7,
            self::BeforeDueDate3 => -3,
            self::DueDate => 0,
            self::GracePeriod3 => 3,
            self::GracePeriod7 => 7,
            self::GracePeriod14 => 14,
            self::SuspensionWarning21 => 21,
        };
    }
}
