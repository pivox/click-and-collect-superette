<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\MobileApplication;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * MOBILE-PUSH #620: device registration (POST /api/mobile/devices, upsert by
 * push_token_hash). Assert\Choice only on ?string fields, never on a typed
 * enum (pattern #26).
 */
final readonly class MobileDeviceRegisterInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        #[SerializedName('push_token')]
        public ?string $pushToken = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [MobileApplication::class, 'values'])]
        public ?string $application = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [MobilePlatform::class, 'values'])]
        public ?string $platform = null,

        #[Assert\Choice(callback: [MobilePushProvider::class, 'values'])]
        public ?string $provider = MobilePushProvider::Expo->value,

        #[Assert\Choice(choices: ['fr', 'ar'])]
        public ?string $locale = 'fr',

        #[Assert\Length(max: 64)]
        public ?string $timezone = null,

        #[Assert\Length(max: 32)]
        #[SerializedName('app_version')]
        public ?string $appVersion = null,

        #[Assert\Length(max: 8)]
        #[SerializedName('os_major_version')]
        public ?string $osMajorVersion = null,
    ) {
    }
}
