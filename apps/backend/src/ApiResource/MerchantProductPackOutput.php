<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantProductPackCreateInput;
use App\Dto\MerchantProductPackUpdateInput;
use App\Entity\Shop;
use App\Processor\AddPackToKadhiaProcessor;
use App\Processor\CreateMerchantProductPackProcessor;
use App\Processor\DeleteMerchantProductPackProcessor;
use App\Processor\UpdateMerchantProductPackProcessor;
use App\Provider\ClientProductPackCollectionProvider;
use App\Provider\MerchantProductPackCollectionProvider;
use App\Provider\MerchantProductPackItemProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/packs',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            input: MerchantProductPackCreateInput::class,
            status: 201,
            read: false,
            processor: CreateMerchantProductPackProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new GetCollection(
            uriTemplate: '/merchant/stores/{storeId}/packs',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            provider: MerchantProductPackCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/packs/{packId}',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
                'packId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            provider: MerchantProductPackItemProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/packs/{packId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'packId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantProductPackUpdateInput::class,
            read: false,
            processor: UpdateMerchantProductPackProcessor::class,
            provider: MerchantProductPackItemProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/stores/{storeId}/packs/{packId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'packId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            read: false,
            processor: DeleteMerchantProductPackProcessor::class,
            provider: MerchantProductPackItemProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new GetCollection(
            uriTemplate: '/stores/{shopId}/packs',
            uriVariables: ['shopId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            provider: ClientProductPackCollectionProvider::class,
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Post(
            uriTemplate: '/me/kadhias/{kadhiaId}/packs/{packId}',
            uriVariables: [
                'kadhiaId' => new Link(fromClass: KadhiaOutput::class, identifiers: ['id']),
                'packId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: false,
            read: false,
            processor: AddPackToKadhiaProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class MerchantProductPackOutput
{
    /**
     * @param ProductPackItemOutput[] $items
     */
    public function __construct(
        public string $id,

        #[SerializedName('store_id')]
        public string $storeId,

        #[SerializedName('name_fr')]
        public string $nameFr,

        #[SerializedName('name_ar')]
        public ?string $nameAr = null,

        public ?string $description = null,

        #[SerializedName('is_active')]
        public bool $isActive = true,

        public array $items = [],

        #[SerializedName('total_price_tnd')]
        public string $totalPriceTnd = '0.000',

        #[SerializedName('created_at')]
        public ?string $createdAt = null,

        #[SerializedName('updated_at')]
        public ?string $updatedAt = null,
    ) {
    }
}
