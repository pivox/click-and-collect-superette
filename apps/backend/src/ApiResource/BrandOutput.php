<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Provider\BrandCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/brands',
            formats: ['json' => ['application/json']],
            provider: BrandCollectionProvider::class,
            normalizationContext: ['groups' => ['brand:read']],
            security: "is_granted('ROLE_USER')",
        ),
    ],
)]
final readonly class BrandOutput
{
    public function __construct(
        #[Groups(['brand:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['brand:read'])]
        public string $name,
        #[Groups(['brand:read'])]
        public string $slug,
    ) {}
}
