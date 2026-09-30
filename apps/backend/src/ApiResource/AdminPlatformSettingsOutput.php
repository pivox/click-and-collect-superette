<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Dto\AdminPlatformSettingsInput;
use App\Processor\AdminUpdatePlatformSettingsProcessor;
use App\Provider\AdminPlatformSettingsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/platform/settings',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_platform_settings:read']],
            provider: AdminPlatformSettingsProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Put(
            uriTemplate: '/admin/platform/settings',
            formats: ['json' => ['application/json']],
            input: AdminPlatformSettingsInput::class,
            output: self::class,
            read: false,
            normalizationContext: ['groups' => ['admin_platform_settings:read']],
            processor: AdminUpdatePlatformSettingsProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminPlatformSettingsOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_platform_settings:read'])]
        public string $id,
        #[Groups(['admin_platform_settings:read'])]
        public string $frontendOrigin,
        #[Groups(['admin_platform_settings:read'])]
        public string $updatedAt,
    ) {
    }
}
