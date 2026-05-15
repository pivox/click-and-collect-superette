<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\PasswordResetConfirmInput;
use App\Dto\PasswordResetRequestInput;
use App\Processor\PasswordResetConfirmProcessor;
use App\Processor\PasswordResetRequestProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/password-reset/request',
            formats: ['json' => ['application/json']],
            input: PasswordResetRequestInput::class,
            normalizationContext: ['groups' => ['password_reset_request:read']],
            status: 202,
            read: false,
            processor: PasswordResetRequestProcessor::class,
        ),
        new Post(
            uriTemplate: '/auth/password-reset/confirm',
            formats: ['json' => ['application/json']],
            input: PasswordResetConfirmInput::class,
            output: false,
            status: 204,
            read: false,
            processor: PasswordResetConfirmProcessor::class,
        ),
    ],
)]
final readonly class PasswordResetOutput
{
    public function __construct(
        #[Groups(['password_reset_request:read'])]
        public string $message,
    ) {
    }
}
