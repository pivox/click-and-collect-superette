<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\StorePublicProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/{storeId}',
            formats: ['json' => ['application/json']],
            provider: StorePublicProvider::class,
            normalizationContext: ['groups' => ['store_public:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class StorePublicOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['store_public:read'])]
        #[SerializedName('id')]
        public string $storeId,
        #[Groups(['store_public:read'])]
        public string $name,
        #[Groups(['store_public:read'])]
        public string $slug,
        #[Groups(['store_public:read'])]
        public ?string $city,
        #[Groups(['store_public:read'])]
        public string $country,
        #[Groups(['store_public:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
    ) {
    }
}
