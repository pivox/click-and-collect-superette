<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\AdminPlatformSettingsOutput;
use App\Entity\PlatformSetting;

final readonly class AdminPlatformSettingsOutputFactory
{
    public function fromSetting(PlatformSetting $setting): AdminPlatformSettingsOutput
    {
        return new AdminPlatformSettingsOutput(
            id: 'platform-settings',
            frontendOrigin: $setting->getFrontendOrigin(),
            updatedAt: $setting->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
