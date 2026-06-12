<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminPromotionCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/promotions',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_promotion_list:read']],
            provider: AdminPromotionCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 20],
                    description: 'Résultats par page (défaut : 20, max : 50).',
                ),
                'promotion_status' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Filtre optionnel : "active" ou "expired" (borne fin de journée Africa/Tunis).',
                ),
                'store_id' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'uuid'],
                    description: 'Filtre optionnel par supérette.',
                ),
                'merchant_id' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'uuid'],
                    description: 'Filtre optionnel par marchand (propriétaire de la supérette).',
                ),
            ],
        ),
    ],
)]
final readonly class AdminPromotionListOutput
{
    /**
     * @param list<AdminPromotionItemOutput> $items
     */
    public function __construct(
        // API Platform identifier only; not part of the serialized collection payload.
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_promotion_list:read'])]
        public array $items,
        #[Groups(['admin_promotion_list:read'])]
        public int $page,
        #[Groups(['admin_promotion_list:read'])]
        public int $limit,
        #[Groups(['admin_promotion_list:read'])]
        public int $total,
    ) {
    }
}
