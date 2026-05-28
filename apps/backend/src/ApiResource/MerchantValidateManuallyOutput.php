<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantValidateManuallyInput;
use App\Entity\Shop;
use App\Processor\MerchantValidateManuallyProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/validate-manually',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: MerchantValidateManuallyOutput::class, identifiers: ['id']),
            ],
            requirements: ['orderId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_validate_manually:read']],
            input: MerchantValidateManuallyInput::class,
            status: 200,
            read: false,
            processor: MerchantValidateManuallyProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantValidateManuallyOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_validate_manually:read'])]
        public string $id,
        #[Groups(['merchant_validate_manually:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_validate_manually:read'])]
        public string $status,
    ) {
    }
}
