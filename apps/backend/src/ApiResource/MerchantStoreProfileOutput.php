<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Dto\MerchantStoreProfileUpdateInput;
use App\Entity\Shop;
use App\Processor\UpdateMerchantStoreProfileProcessor;
use App\Provider\MerchantStoreProfileProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Store profile editable by its owning merchant (MS-002).
 *
 * Read exposes the shop "shopfront" identity; PATCH lets the owner update
 * name, address, city, phone, logo and cover. Ownership is enforced in both
 * the provider and the processor.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/profile',
            formats: ['json' => ['application/json']],
            provider: MerchantStoreProfileProvider::class,
            normalizationContext: ['groups' => ['merchant_store_profile:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/profile',
            formats: ['json' => ['application/json']],
            input: MerchantStoreProfileUpdateInput::class,
            output: self::class,
            read: false,
            processor: UpdateMerchantStoreProfileProcessor::class,
            normalizationContext: ['groups' => ['merchant_store_profile:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantStoreProfileOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_store_profile:read'])]
        #[SerializedName('id')]
        public string $storeId,
        #[Groups(['merchant_store_profile:read'])]
        public string $name,
        #[Groups(['merchant_store_profile:read'])]
        public ?string $address,
        #[Groups(['merchant_store_profile:read'])]
        public ?string $city,
        #[Groups(['merchant_store_profile:read'])]
        public ?string $phone,
        #[Groups(['merchant_store_profile:read'])]
        #[SerializedName('logo_url')]
        public ?string $logoUrl,
        #[Groups(['merchant_store_profile:read'])]
        #[SerializedName('cover_url')]
        public ?string $coverUrl,
        #[Groups(['merchant_store_profile:read'])]
        public bool $active,
    ) {
    }

    public static function fromShop(Shop $shop): self
    {
        return new self(
            storeId: $shop->getId()->toRfc4122(),
            name: $shop->getName(),
            address: $shop->getAddress(),
            city: $shop->getCity(),
            phone: $shop->getPhone(),
            logoUrl: $shop->getLogoUrl(),
            coverUrl: $shop->getCoverUrl(),
            active: $shop->isActive(),
        );
    }
}
