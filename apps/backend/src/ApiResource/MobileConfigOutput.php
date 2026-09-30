<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\MobileConfigProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Public mobile configuration (#618): minimum app versions per application and
 * platform, plus announced maintenance mode. Values come from configuration
 * (env parameters) with fail-open defaults — no database table in V1.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/mobile/config',
            formats: ['json' => ['application/json']],
            provider: MobileConfigProvider::class,
            cacheHeaders: [
                'public' => true,
                'max_age' => 300,
            ],
            normalizationContext: ['groups' => ['mobile_config:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class MobileConfigOutput
{
    /**
     * @param array{client: array{android: string, ios: string}, merchant: array{android: string, ios: string}} $minimumAppVersion
     * @param array{enabled: bool, message_fr: ?string, message_ar: ?string}                                    $maintenance
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['mobile_config:read'])]
        #[SerializedName('minimum_app_version')]
        public array $minimumAppVersion,
        #[Groups(['mobile_config:read'])]
        public array $maintenance,
    ) {
    }
}
