<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Snapshot of a shop's shared photo-import quota (CATALOG-AI-001, issue #638).
 */
final readonly class CatalogPhotoQuotaBalance
{
    public function __construct(
        public int $granted,
        public int $reserved,
        public int $consumed,
        public int $available,
    ) {
    }
}
