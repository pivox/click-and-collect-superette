<?php

declare(strict_types=1);

namespace App\Enum;

enum BillingDocumentType: string
{
    case MonthlyStatement = 'monthly_statement';

    public function labelFr(): string
    {
        return match ($this) {
            self::MonthlyStatement => 'Document mensuel interne non fiscal',
        };
    }
}
