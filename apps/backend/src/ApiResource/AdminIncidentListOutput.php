<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminIncidentCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/incidents',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_incident_list:read']],
            provider: AdminIncidentCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
                'merchant' => new QueryParameter(schema: ['type' => 'string']),
                'client' => new QueryParameter(schema: ['type' => 'string']),
                'status' => new QueryParameter(schema: ['type' => 'string']),
            ],
        ),
    ],
)]
final readonly class AdminIncidentListOutput
{
    /**
     * @param list<AdminIncidentOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_incident_list:read'])]
        public array $items,
        #[Groups(['admin_incident_list:read'])]
        public int $page,
        #[Groups(['admin_incident_list:read'])]
        public int $limit,
        #[Groups(['admin_incident_list:read'])]
        public int $total,
    ) {
    }
}
