<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminMerchantOrganizationCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Additive admin collection of merchant organizations (MERCHANT-TEAM-002).
 *
 * Lists each commercial organization exactly once, regardless of how many
 * login accounts it holds. The historical per-account `/admin/merchants`
 * collection is unchanged: no identifier silently changes nature.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/merchant-organizations',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_merchant_organization:read']],
            provider: AdminMerchantOrganizationCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'default' => 1]),
                'limit' => new QueryParameter(schema: ['type' => 'integer', 'default' => 20]),
            ],
        ),
    ],
)]
final readonly class AdminMerchantOrganizationListOutput
{
    /**
     * @param list<AdminMerchantOrganizationOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_merchant_organization:read'])]
        public array $items,
        #[Groups(['admin_merchant_organization:read'])]
        public int $page,
        #[Groups(['admin_merchant_organization:read'])]
        public int $limit,
        #[Groups(['admin_merchant_organization:read'])]
        public int $total,
    ) {
    }
}
