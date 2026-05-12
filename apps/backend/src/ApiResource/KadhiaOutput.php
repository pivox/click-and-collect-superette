<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use App\Dto\KadhiaLineUpsertInput;
use App\Dto\KadhiaPatchInput;
use App\Entity\MerchantProduct;
use App\Entity\Shop;
use App\Processor\PatchKadhiaNotesProcessor;
use App\Processor\RemoveKadhiaLineProcessor;
use App\Processor\UpsertKadhiaLineProcessor;
use App\Provider\KadhiaProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/stores/{storeId}/kadhia',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['kadhia:read']],
            provider: KadhiaProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Patch(
            uriTemplate: '/me/stores/{storeId}/kadhia',
            uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
            formats: ['json' => ['application/json']],
            input: KadhiaPatchInput::class,
            normalizationContext: ['groups' => ['kadhia:read']],
            status: 200,
            read: false,
            processor: PatchKadhiaNotesProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Put(
            uriTemplate: '/me/stores/{storeId}/kadhia/lines/{merchantProductId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: KadhiaLineUpsertInput::class,
            normalizationContext: ['groups' => ['kadhia:read']],
            status: 200,
            read: false,
            processor: UpsertKadhiaLineProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Delete(
            uriTemplate: '/me/stores/{storeId}/kadhia/lines/{merchantProductId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
            ],
            read: false,
            output: false,
            processor: RemoveKadhiaLineProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class KadhiaOutput
{
    /**
     * @param list<KadhiaLineOutput> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['kadhia:read'])]
        public string $id,
        #[Groups(['kadhia:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['kadhia:read'])]
        public string $status,
        #[Groups(['kadhia:read'])]
        public ?string $notes,
        #[Groups(['kadhia:read'])]
        public array $lines,
        #[Groups(['kadhia:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['kadhia:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['kadhia:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
