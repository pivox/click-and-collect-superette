<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\StoreCatalogProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/{storeId}/catalog',
            formats: ['json' => ['application/json']],
            provider: StoreCatalogProvider::class,
            normalizationContext: ['groups' => ['store_catalog:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class StoreCatalogOutput
{
    /**
     * @param list<StoreCatalogProductOutput> $items
     */
    public function __construct(
        #[Groups(['store_catalog:read'])]
        public array $items,
        #[ApiProperty(identifier: true)]
        public ?string $storeId = null,
    ) {
    }
}
