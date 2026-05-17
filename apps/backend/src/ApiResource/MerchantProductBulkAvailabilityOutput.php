<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\MerchantProductBulkAvailabilityInput;
use App\Processor\MerchantProductBulkAvailabilityProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/products/bulk-availability',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantProductBulkAvailabilityInput::class,
            status: 200,
            read: false,
            processor: MerchantProductBulkAvailabilityProcessor::class,
            normalizationContext: ['groups' => ['merchant_product_bulk_availability:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantProductBulkAvailabilityOutput
{
    /**
     * @param list<string> $merchantProductIds
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_product_bulk_availability:read'])]
        #[SerializedName('updated_count')]
        public int $updatedCount,
        #[Groups(['merchant_product_bulk_availability:read'])]
        #[SerializedName('is_available')]
        public bool $isAvailable,
        #[Groups(['merchant_product_bulk_availability:read'])]
        #[SerializedName('merchant_note')]
        public ?string $merchantNote,
        #[Groups(['merchant_product_bulk_availability:read'])]
        #[SerializedName('merchant_product_ids')]
        public array $merchantProductIds,
    ) {
    }
}
