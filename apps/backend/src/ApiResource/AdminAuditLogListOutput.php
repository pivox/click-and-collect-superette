<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminAuditLogCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/audit-logs',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_audit_log_list:read']],
            provider: AdminAuditLogCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'action' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtre par action (ex. merchant.suspend).',
                ),
                'resource_type' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtre par type de ressource (ex. merchant, store).',
                ),
                'resource_id' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtre par UUID de la ressource concernée.',
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 20],
                    description: 'Résultats par page (défaut : 20, max : 50).',
                ),
            ],
        ),
    ],
)]
final readonly class AdminAuditLogListOutput
{
    /**
     * @param list<AdminAuditLogItemOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_audit_log_list:read'])]
        public array $items,
        #[Groups(['admin_audit_log_list:read'])]
        public int $page,
        #[Groups(['admin_audit_log_list:read'])]
        public int $limit,
        #[Groups(['admin_audit_log_list:read'])]
        public int $total,
    ) {
    }
}
