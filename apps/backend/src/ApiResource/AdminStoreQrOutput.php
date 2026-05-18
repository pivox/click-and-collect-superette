<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Processor\AdminRegenerateStoreQrProcessor;
use App\Provider\AdminStoreQrProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/stores/{storeId<[0-9a-fA-F\-]{32,36}>}/qr-code',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_store_qr:read']],
            provider: AdminStoreQrProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/admin/stores/{storeId<[0-9a-fA-F\-]{32,36}>}/regenerate-qr',
            uriVariables: [
                'storeId' => new Link(fromClass: self::class, identifiers: ['storeId']),
            ],
            formats: ['json' => ['application/json']],
            status: 200,
            input: false,
            normalizationContext: ['groups' => ['admin_store_qr:read']],
            processor: AdminRegenerateStoreQrProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminStoreQrOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['admin_store_qr:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['admin_store_qr:read'])]
        #[SerializedName('store_name')]
        public string $storeName,
        #[Groups(['admin_store_qr:read'])]
        public string $slug,
        #[Groups(['admin_store_qr:read'])]
        #[SerializedName('qr_code_token')]
        public string $qrCodeToken,
        #[Groups(['admin_store_qr:read'])]
        #[SerializedName('target_url')]
        public string $targetUrl,
    ) {
    }
}
