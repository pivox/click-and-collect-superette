<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use ApiPlatform\Metadata\Get;
use App\Provider\MobileConfigProvider;
use PHPUnit\Framework\TestCase;

final class MobileConfigProviderTest extends TestCase
{
    public function testFailOpenDefaultsExposeNoConstraintAndDisabledMaintenance(): void
    {
        $provider = new MobileConfigProvider(
            mobileMinVersionClientAndroid: '0.0.0',
            mobileMinVersionClientIos: '0.0.0',
            mobileMinVersionMerchantAndroid: '0.0.0',
            mobileMinVersionMerchantIos: '0.0.0',
            mobileMaintenanceEnabled: false,
            mobileMaintenanceMessageFr: '',
            mobileMaintenanceMessageAr: '',
        );

        $output = $provider->provide(new Get());

        self::assertSame([
            'client' => ['android' => '0.0.0', 'ios' => '0.0.0'],
            'merchant' => ['android' => '0.0.0', 'ios' => '0.0.0'],
        ], $output->minimumAppVersion);
        self::assertSame([
            'enabled' => false,
            'message_fr' => null,
            'message_ar' => null,
        ], $output->maintenance);
    }

    public function testConfiguredVersionsAndMaintenanceMessagesAreExposedPerAppAndPlatform(): void
    {
        $provider = new MobileConfigProvider(
            mobileMinVersionClientAndroid: '1.2.0',
            mobileMinVersionClientIos: '1.1.0',
            mobileMinVersionMerchantAndroid: '2.0.0',
            mobileMinVersionMerchantIos: '2.0.1',
            mobileMaintenanceEnabled: true,
            mobileMaintenanceMessageFr: 'Maintenance en cours, revenez vers 22h.',
            mobileMaintenanceMessageAr: 'صيانة جارية، عاودوا المحاولة على الساعة 22.',
        );

        $output = $provider->provide(new Get());

        self::assertSame('1.2.0', $output->minimumAppVersion['client']['android']);
        self::assertSame('1.1.0', $output->minimumAppVersion['client']['ios']);
        self::assertSame('2.0.0', $output->minimumAppVersion['merchant']['android']);
        self::assertSame('2.0.1', $output->minimumAppVersion['merchant']['ios']);
        self::assertTrue($output->maintenance['enabled']);
        self::assertSame('Maintenance en cours, revenez vers 22h.', $output->maintenance['message_fr']);
        self::assertSame('صيانة جارية، عاودوا المحاولة على الساعة 22.', $output->maintenance['message_ar']);
    }
}
