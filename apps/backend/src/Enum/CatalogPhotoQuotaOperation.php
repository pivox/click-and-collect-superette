<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Immutable journal operations for the merchant photo quota ledger
 * (CATALOG-AI-001, issue #638).
 *
 * - reservation : one source photo registered, debits the available balance
 * - release     : a reservation freed before any usable result existed
 * - consumption : a reservation finalized by a usable AI result (CATALOG-AI-004);
 *                 does not change the balance again, it only stops the
 *                 reservation from ever being released
 * - admin_adjustment : manual correction, not tied to a specific photo
 */
enum CatalogPhotoQuotaOperation: string
{
    case Reservation = 'reservation';
    case Release = 'release';
    case Consumption = 'consumption';
    case AdminAdjustment = 'admin_adjustment';
}
