<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantRedeemByCodeInput;
use App\Entity\Shop;
use App\Processor\MerchantRedeemByCodeProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/redeem-by-code',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_redeem_by_code:read']],
            input: MerchantRedeemByCodeInput::class,
            status: 200,
            read: false,
            processor: MerchantRedeemByCodeProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantRedeemByCodeOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_redeem_by_code:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_redeem_by_code:read'])]
        public string $status,
    ) {
    }
}
