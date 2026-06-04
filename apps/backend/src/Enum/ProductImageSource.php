<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Origin of a product image. Used by the shared image pipeline to decide the
 * default review status of a freshly stored image (see ProductImageStatus).
 *
 * Only AdminUpload is allowed to produce an officially Verified image. Every
 * other source (AI enrichment, merchant contribution, external sources) must
 * stay in a review state until an admin validates it.
 */
enum ProductImageSource: string
{
    case AdminUpload = 'admin_upload';
    case AiEnrichment = 'ai_enrichment';
    case MerchantContribution = 'merchant_contribution';
    case OpenSource = 'open_source';
    case ExternalAuthorizedSource = 'external_authorized_source';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Whether this source may directly produce an officially verified image.
     * Only a validated admin upload is trusted; the AI and contribution flows
     * never auto-promote an image to the official referential picture.
     */
    public function canProduceVerifiedImage(): bool
    {
        return self::AdminUpload === $this;
    }

    public function defaultStatus(): ProductImageStatus
    {
        return match ($this) {
            self::AdminUpload => ProductImageStatus::Verified,
            default => ProductImageStatus::NeedsReview,
        };
    }
}
