<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\ShopOpeningHoursPatchInput;
use App\Entity\Shop;
use App\Processor\UpdateMerchantShopOpeningHoursProcessor;
use App\Provider\MerchantShopOpeningHoursProvider;
use App\Provider\ShopOpeningHoursProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/{storeId}/opening-hours',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: ShopOpeningHoursProvider::class,
            normalizationContext: ['groups' => ['shop_opening_hours:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/opening-hours',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            provider: MerchantShopOpeningHoursProvider::class,
            normalizationContext: ['groups' => ['shop_opening_hours:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/opening-hours',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: ShopOpeningHoursPatchInput::class,
            output: self::class,
            read: false,
            processor: UpdateMerchantShopOpeningHoursProcessor::class,
            normalizationContext: ['groups' => ['shop_opening_hours:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class ShopOpeningHoursOutput
{
    /**
     * @param array<string, mixed>|null $openingHours
     */
    public function __construct(
        #[Groups(['shop_opening_hours:read'])]
        #[SerializedName('store_id')]
        #[ApiProperty(identifier: true)]
        public string $storeId,
        #[Groups(['shop_opening_hours:read'])]
        #[SerializedName('opening_hours')]
        public ?array $openingHours,
    ) {
    }
}
