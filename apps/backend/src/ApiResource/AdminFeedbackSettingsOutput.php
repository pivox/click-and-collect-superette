<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Dto\AdminFeedbackSettingsInput;
use App\Processor\AdminUpdateFeedbackSettingsProcessor;
use App\Provider\AdminFeedbackSettingsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/feedback/settings',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_feedback_settings:read']],
            provider: AdminFeedbackSettingsProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Put(
            uriTemplate: '/admin/feedback/settings',
            formats: ['json' => ['application/json']],
            input: AdminFeedbackSettingsInput::class,
            output: self::class,
            read: false,
            normalizationContext: ['groups' => ['admin_feedback_settings:read']],
            processor: AdminUpdateFeedbackSettingsProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
    ],
)]
final readonly class AdminFeedbackSettingsOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $globalEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $clientEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $merchantEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $adminEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $clientAreaEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $merchantAreaEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $adminAreaEnabled,
        #[Groups(['admin_feedback_settings:read'])]
        public bool $requireAuthenticatedUser,
        #[Groups(['admin_feedback_settings:read'])]
        public string $updatedAt,
    ) {
    }
}
