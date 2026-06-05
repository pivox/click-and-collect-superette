<?php

declare(strict_types=1);

namespace App\Enum;

enum IncidentType: string
{
    case CustomerAbsent = 'customer_absent';
    case MerchantLate = 'merchant_late';
    case MissingProduct = 'missing_product';
    case PriceError = 'price_error';
    case OrderNotPrepared = 'order_not_prepared';
    case QrIssue = 'qr_issue';
    case LateCancellation = 'late_cancellation';
    case Other = 'other';
}
