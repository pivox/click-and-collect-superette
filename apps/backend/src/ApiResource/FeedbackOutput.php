<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\FeedbackCreateInput;
use App\Processor\CreateFeedbackProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/feedback',
            formats: ['json' => ['application/json']],
            input: FeedbackCreateInput::class,
            output: self::class,
            normalizationContext: ['groups' => ['feedback:read']],
            processor: CreateFeedbackProcessor::class,
            security: "is_granted('ROLE_USER')",
            status: 201,
            validate: true,
        ),
    ],
)]
final readonly class FeedbackOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public string $id,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public string $status,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public string $type,
        #[Groups(['admin_feedback:read'])]
        public string $message,
        #[Groups(['admin_feedback_list:read'])]
        public string $messageExcerpt,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public string $appArea,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public ?string $appSubArea,
        #[Groups(['admin_feedback:read', 'admin_feedback_list:read'])]
        public string $userRole,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public FeedbackUserOutput $user,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public ?FeedbackShopOutput $shop,
        #[Groups(['admin_feedback:read', 'admin_feedback_list:read'])]
        public ?string $pageUrl,
        #[Groups(['admin_feedback:read'])]
        public ?string $routeName,
        #[Groups(['admin_feedback:read'])]
        public ?string $pageTitle,
        #[Groups(['admin_feedback:read'])]
        public ?string $locale,
        #[Groups(['admin_feedback:read'])]
        public ?int $viewportWidth,
        #[Groups(['admin_feedback:read'])]
        public ?int $viewportHeight,
        #[Groups(['admin_feedback:read'])]
        public ?string $userAgent,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public bool $contactConsent,
        #[Groups(['admin_feedback:read'])]
        public ?string $adminNote,
        #[Groups(['admin_feedback:read'])]
        public ?string $readAt,
        #[Groups(['admin_feedback:read'])]
        public ?string $resolvedAt,
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public string $createdAt,
        #[Groups(['admin_feedback:read'])]
        public string $updatedAt,
    ) {
    }
}
