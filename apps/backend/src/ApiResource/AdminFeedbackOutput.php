<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\AdminFeedbackStatusInput;
use App\Processor\AdminFeedbackStatusProcessor;
use App\Provider\AdminFeedbackItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/feedbacks/{feedbackId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'feedbackId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            output: FeedbackOutput::class,
            normalizationContext: ['groups' => ['admin_feedback:read']],
            provider: AdminFeedbackItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            name: 'admin_feedback_read',
            uriTemplate: '/admin/feedbacks/{feedbackId<[0-9a-fA-F\-]{32,36}>}/read',
            uriVariables: [
                'feedbackId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            output: FeedbackOutput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_feedback:read']],
            processor: AdminFeedbackStatusProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            name: 'admin_feedback_unread',
            uriTemplate: '/admin/feedbacks/{feedbackId<[0-9a-fA-F\-]{32,36}>}/unread',
            uriVariables: [
                'feedbackId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            output: FeedbackOutput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_feedback:read']],
            processor: AdminFeedbackStatusProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            name: 'admin_feedback_resolve',
            uriTemplate: '/admin/feedbacks/{feedbackId<[0-9a-fA-F\-]{32,36}>}/resolve',
            uriVariables: [
                'feedbackId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminFeedbackStatusInput::class,
            output: FeedbackOutput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_feedback:read']],
            processor: AdminFeedbackStatusProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            validate: true,
        ),
        new Patch(
            name: 'admin_feedback_reopen',
            uriTemplate: '/admin/feedbacks/{feedbackId<[0-9a-fA-F\-]{32,36}>}/reopen',
            uriVariables: [
                'feedbackId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            output: FeedbackOutput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_feedback:read']],
            processor: AdminFeedbackStatusProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminFeedbackOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_feedback:read'])]
        public string $id,
    ) {
    }
}
