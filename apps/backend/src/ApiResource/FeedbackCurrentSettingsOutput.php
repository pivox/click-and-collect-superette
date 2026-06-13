<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\FeedbackCurrentSettingsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/feedback/settings/current',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['feedback_settings_current:read']],
            provider: FeedbackCurrentSettingsProvider::class,
            security: "is_granted('ROLE_USER')",
            parameters: [
                'appArea' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['client', 'merchant', 'admin']]),
                'appSubArea' => new QueryParameter(schema: ['type' => 'string']),
            ],
        ),
    ],
)]
final readonly class FeedbackCurrentSettingsOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['feedback_settings_current:read'])]
        public bool $enabled,
        #[Groups(['feedback_settings_current:read'])]
        public string $appArea,
        #[Groups(['feedback_settings_current:read'])]
        public ?string $appSubArea,
        #[Groups(['feedback_settings_current:read'])]
        public bool $requireAuthenticatedUser,
    ) {
    }
}
