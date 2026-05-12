<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\StoreByQrProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/by-qr/{qrCodeToken}',
            formats: ['json' => ['application/json']],
            provider: StoreByQrProvider::class,
            normalizationContext: ['groups' => ['store_by_qr:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class StoreByQrOutput
{
    public function __construct(
        #[Groups(['store_by_qr:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['store_by_qr:read'])]
        public string $name,
        #[Groups(['store_by_qr:read'])]
        public string $slug,
        #[Groups(['store_by_qr:read'])]
        public ?string $city,
        #[Groups(['store_by_qr:read'])]
        public string $country,
        #[Groups(['store_by_qr:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
        #[Groups(['store_by_qr:read'])]
        #[SerializedName('theme_url')]
        public string $themeUrl,
        #[Groups(['store_by_qr:read'])]
        #[SerializedName('catalog_url')]
        public string $catalogUrl,
        #[ApiProperty(identifier: true)]
        public ?string $qrCodeToken = null,
    ) {
    }
}
