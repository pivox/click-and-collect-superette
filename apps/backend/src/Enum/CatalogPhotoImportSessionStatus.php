<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Session lifecycle (CATALOG-AI-001, issue #638).
 *
 * Only Draft and Cancelled are reachable through this issue's endpoints.
 * Queued/Processing/ReviewReady/PartiallyCompleted/Completed/Failed belong to
 * the async orchestration built in CATALOG-AI-004 (#641) — modeled here so the
 * column never needs a disruptive migration when that lands.
 */
enum CatalogPhotoImportSessionStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case ReviewReady = 'review_ready';
    case PartiallyCompleted = 'partially_completed';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return \in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }
}
