<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Provider\CategoryCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/categories',
            formats: ['json' => ['application/json']],
            provider: CategoryCollectionProvider::class,
            normalizationContext: ['groups' => ['category:read']],
            security: "is_granted('ROLE_USER')",
        ),
    ],
)]
final readonly class CategoryOutput
{
    public function __construct(
        #[Groups(['category:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['category:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['category:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['category:read'])]
        public string $slug,
        #[Groups(['category:read'])]
        #[SerializedName('parent_id')]
        public ?string $parentId,
        #[Groups(['category:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
    ) {
    }
}
