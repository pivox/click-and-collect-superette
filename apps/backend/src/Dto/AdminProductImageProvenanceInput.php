<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ProductImageLicenseCode;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Provenance / usage-rights fields of a product image (PRODUCT-IMAGE-004).
 *
 * Used both as the API Platform input of PATCH /admin/product-images/{id}/provenance
 * (every field optional — only keys present in the payload are applied) and as the
 * validated bag of the optional provenance form fields of the admin upload endpoint.
 *
 * license_code stays a ?string validated by Assert\Choice (backend-patterns #17/#26:
 * never Assert\Choice on a PHP enum-typed field); processors convert it with
 * ProductImageLicenseCode::from() (pattern #14).
 */
final class AdminProductImageProvenanceInput
{
    #[Assert\Choice(callback: [ProductImageLicenseCode::class, 'values'])]
    #[SerializedName('license_code')]
    public ?string $licenseCode = null;

    #[Assert\Length(max: 255)]
    #[SerializedName('source_name')]
    public ?string $sourceName = null;

    #[Assert\Url(requireTld: true, protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    #[SerializedName('source_url')]
    public ?string $sourceUrl = null;

    #[Assert\Length(max: 5000)]
    #[SerializedName('attribution_text')]
    public ?string $attributionText = null;

    #[Assert\Length(max: 255)]
    #[SerializedName('permission_reference')]
    public ?string $permissionReference = null;

    /** ISO 8601 date or datetime (e.g. 2026-09-28 or 2026-09-28T10:00:00+01:00). */
    #[Assert\Length(max: 64)]
    #[SerializedName('captured_at')]
    public ?string $capturedAt = null;
}
