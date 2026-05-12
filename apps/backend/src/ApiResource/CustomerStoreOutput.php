<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\CustomerStoreFavoriteInput;
use App\Dto\CustomerStoreVisitInput;
use App\Entity\Shop;
use App\Processor\HideCustomerStoreProcessor;
use App\Processor\RecordStoreVisitProcessor;
use App\Processor\UpdateStoreFavoriteProcessor;
use App\Provider\CustomerStoreCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/me/stores',
            formats: ['json' => ['application/json']],
            provider: CustomerStoreCollectionProvider::class,
            normalizationContext: ['groups' => ['customer_store:read']],
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Post(
            uriTemplate: '/me/stores/{storeId}/visit',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: CustomerStoreVisitInput::class,
            normalizationContext: ['groups' => ['customer_store:read']],
            status: 200,
            read: false,
            processor: RecordStoreVisitProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Patch(
            uriTemplate: '/me/stores/{storeId}/favorite',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: CustomerStoreFavoriteInput::class,
            normalizationContext: ['groups' => ['customer_store:read']],
            status: 200,
            read: false,
            processor: UpdateStoreFavoriteProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Delete(
            uriTemplate: '/me/stores/{storeId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: HideCustomerStoreProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerStoreOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['customer_store:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['customer_store:read'])]
        public string $name,
        #[Groups(['customer_store:read'])]
        public string $slug,
        #[Groups(['customer_store:read'])]
        public ?string $city,
        #[Groups(['customer_store:read'])]
        public string $country,
        #[Groups(['customer_store:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
        #[Groups(['customer_store:read'])]
        #[SerializedName('is_favorite')]
        public bool $isFavorite,
        #[Groups(['customer_store:read'])]
        public string $source,
        #[Groups(['customer_store:read'])]
        #[SerializedName('first_seen_at')]
        public string $firstSeenAt,
        #[Groups(['customer_store:read'])]
        #[SerializedName('last_seen_at')]
        public string $lastSeenAt,
    ) {
    }
}
