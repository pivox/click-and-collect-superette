<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\AdminFeedbackSettingsOutput;
use App\Entity\FeedbackSetting;

final readonly class AdminFeedbackSettingsOutputFactory
{
    public function fromSetting(FeedbackSetting $setting): AdminFeedbackSettingsOutput
    {
        return new AdminFeedbackSettingsOutput(
            id: $setting->getId()->toRfc4122(),
            globalEnabled: $setting->isGlobalEnabled(),
            clientEnabled: $setting->isClientEnabled(),
            merchantEnabled: $setting->isMerchantEnabled(),
            adminEnabled: $setting->isAdminEnabled(),
            clientAreaEnabled: $setting->isClientAreaEnabled(),
            merchantAreaEnabled: $setting->isMerchantAreaEnabled(),
            adminAreaEnabled: $setting->isAdminAreaEnabled(),
            requireAuthenticatedUser: $setting->requiresAuthenticatedUser(),
            updatedAt: $setting->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
