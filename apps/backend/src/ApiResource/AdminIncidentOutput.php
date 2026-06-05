<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\AdminIncidentNoteCreateInput;
use App\Processor\AdminAddIncidentNoteProcessor;
use App\Processor\AdminCloseIncidentProcessor;
use App\Processor\AdminStartIncidentProcessingProcessor;
use App\Provider\AdminIncidentItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/incidents/{incidentId<[0-9a-fA-F\-]{32,36}>}',
            uriVariables: [
                'incidentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_incident:read']],
            provider: AdminIncidentItemProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/admin/incidents/{incidentId<[0-9a-fA-F\-]{32,36}>}/notes',
            uriVariables: [
                'incidentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: AdminIncidentNoteCreateInput::class,
            read: false,
            normalizationContext: ['groups' => ['admin_incident:read']],
            processor: AdminAddIncidentNoteProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
        new Patch(
            uriTemplate: '/admin/incidents/{incidentId<[0-9a-fA-F\-]{32,36}>}/start-processing',
            uriVariables: [
                'incidentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            read: false,
            normalizationContext: ['groups' => ['admin_incident:read']],
            processor: AdminStartIncidentProcessingProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/admin/incidents/{incidentId<[0-9a-fA-F\-]{32,36}>}/close',
            uriVariables: [
                'incidentId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            read: false,
            normalizationContext: ['groups' => ['admin_incident:read']],
            processor: AdminCloseIncidentProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminIncidentOutput
{
    /**
     * @param list<AdminIncidentNoteOutput>         $notes
     * @param list<AdminIncidentHistoryEntryOutput> $history
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        public string $id,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('merchant_id')]
        public string $merchantId,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('merchant_email')]
        public string $merchantEmail,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('customer_id')]
        public string $customerId,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('customer_email')]
        public string $customerEmail,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        public string $type,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        public string $status,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('occurred_at')]
        public string $occurredAt,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
        #[Groups(['admin_incident:read', 'admin_incident_list:read'])]
        #[SerializedName('closed_at')]
        public ?string $closedAt,
        #[Groups(['admin_incident:read'])]
        public array $notes,
        #[Groups(['admin_incident:read'])]
        public array $history,
    ) {
    }
}
