<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * MOBILE-PUSH #620: device metadata update (PATCH /api/mobile/devices/{id}).
 * Every field is optional — an absent (null) field is left untouched; the
 * call always refreshes last_seen_at.
 */
final readonly class MobileDeviceUpdateInput
{
    public function __construct(
        #[Assert\Choice(choices: ['fr', 'ar'])]
        public ?string $locale = null,

        #[Assert\Length(max: 64)]
        public ?string $timezone = null,

        #[Assert\Length(max: 32)]
        #[SerializedName('app_version')]
        public ?string $appVersion = null,

        public ?bool $enabled = null,
    ) {
    }
}
