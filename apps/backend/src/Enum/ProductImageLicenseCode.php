<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Usage rights of a product image (PRODUCT-IMAGE-004 provenance registry).
 *
 * The license is orthogonal to the origin (ProductImageSource): the source says
 * where the bytes come from, the license says what the platform is allowed to do
 * with them. An image whose rights are Unknown can never become the official
 * (verified) picture of a product reference.
 */
enum ProductImageLicenseCode: string
{
    case PlatformOwned = 'platform_owned';
    case MerchantAuthorized = 'merchant_authorized';
    case ManufacturerAuthorized = 'manufacturer_authorized';
    case DistributorAuthorized = 'distributor_authorized';
    case CcBy = 'cc_by';
    case CcBySa = 'cc_by_sa';
    case PublicDomain = 'public_domain';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Whether an image under this license may be published as the official
     * referential picture. Every documented license allows it; only an unknown
     * provenance blocks the official publication.
     */
    public function allowsOfficialPublication(): bool
    {
        return self::Unknown !== $this;
    }

    /**
     * Creative Commons attribution licenses require the attribution text to be
     * displayed wherever the image is exposed.
     */
    public function requiresAttribution(): bool
    {
        return self::CcBy === $this || self::CcBySa === $this;
    }
}
