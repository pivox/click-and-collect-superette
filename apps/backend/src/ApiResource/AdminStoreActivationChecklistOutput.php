<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Provider\AdminStoreActivationChecklistProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/stores/{storeId<[0-9a-fA-F\-]{32,36}>}/activation-checklist',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_store_activation_checklist:read']],
            provider: AdminStoreActivationChecklistProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminStoreActivationChecklistOutput
{
    /**
     * @param list<AdminStoreActivationChecklistStepOutput> $steps
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_store_activation_checklist:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['admin_store_activation_checklist:read'])]
        #[SerializedName('store_name')]
        public string $storeName,
        #[Groups(['admin_store_activation_checklist:read'])]
        public bool $ready,
        #[Groups(['admin_store_activation_checklist:read'])]
        #[SerializedName('minimum_catalog_products')]
        public int $minimumCatalogProducts,
        #[Groups(['admin_store_activation_checklist:read'])]
        #[SerializedName('required_completed_count')]
        public int $requiredCompletedCount,
        #[Groups(['admin_store_activation_checklist:read'])]
        #[SerializedName('required_total_count')]
        public int $requiredTotalCount,
        #[Groups(['admin_store_activation_checklist:read'])]
        public array $steps,
    ) {
    }
}
