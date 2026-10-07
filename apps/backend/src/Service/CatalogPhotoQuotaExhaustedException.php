<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Thrown when a shop has no available photo-import credit left
 * (CATALOG-AI-001, issue #638). Mapped to HTTP 422 by the caller.
 */
final class CatalogPhotoQuotaExhaustedException extends \RuntimeException
{
}
