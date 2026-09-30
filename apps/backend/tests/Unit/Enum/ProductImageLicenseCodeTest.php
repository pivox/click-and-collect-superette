<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ProductImageLicenseCode;
use PHPUnit\Framework\TestCase;

final class ProductImageLicenseCodeTest extends TestCase
{
    public function testValuesExposesEveryCase(): void
    {
        self::assertSame([
            'platform_owned',
            'merchant_authorized',
            'manufacturer_authorized',
            'distributor_authorized',
            'cc_by',
            'cc_by_sa',
            'public_domain',
            'unknown',
        ], ProductImageLicenseCode::values());
    }

    public function testEveryLicenseExceptUnknownAllowsOfficialPublication(): void
    {
        foreach (ProductImageLicenseCode::cases() as $license) {
            self::assertSame(
                ProductImageLicenseCode::Unknown !== $license,
                $license->allowsOfficialPublication(),
                \sprintf('Unexpected allowsOfficialPublication() for %s', $license->value),
            );
        }
    }

    public function testOnlyCreativeCommonsLicensesRequireAttribution(): void
    {
        $requiring = array_values(array_filter(
            ProductImageLicenseCode::cases(),
            static fn (ProductImageLicenseCode $license): bool => $license->requiresAttribution(),
        ));

        self::assertSame([ProductImageLicenseCode::CcBy, ProductImageLicenseCode::CcBySa], $requiring);
    }
}
