<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Review lifecycle of a product image.
 *
 * - candidate / needs_review : stored but not yet trusted as the official picture
 * - verified                 : official referential image, safe to expose everywhere
 * - rejected / archived      : kept for traceability, never exposed in catalogs
 */
enum ProductImageStatus: string
{
    case Candidate = 'candidate';
    case NeedsReview = 'needs_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Only verified images are exposed in the public/merchant catalogs.
     */
    public function isPubliclyExposable(): bool
    {
        return self::Verified === $this;
    }
}
