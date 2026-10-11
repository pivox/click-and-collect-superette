<?php

declare(strict_types=1);

namespace App\Enum;

enum BillingDocumentType: string
{
    case AnnualStatement = 'annual_statement';

    case MonthlyStatement = 'monthly_statement';

    public function labelFr(): string
    {
        return match ($this) {
            self::AnnualStatement => 'Document annuel interne non fiscal',
            self::MonthlyStatement => 'Document mensuel interne non fiscal',
        };
    }
}
