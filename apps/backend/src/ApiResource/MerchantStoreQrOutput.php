<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Provider\MerchantStoreQrProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId<[0-9a-fA-F\-]{32,36}>}/qr-code',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_store_qr:read']],
            provider: MerchantStoreQrProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantStoreQrOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_store_qr:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_store_qr:read'])]
        #[SerializedName('store_name')]
        public string $storeName,
        #[Groups(['merchant_store_qr:read'])]
        public string $slug,
        #[Groups(['merchant_store_qr:read'])]
        #[SerializedName('qr_code_token')]
        public string $qrCodeToken,
        #[Groups(['merchant_store_qr:read'])]
        #[SerializedName('target_url')]
        public string $targetUrl,
    ) {
    }
}
