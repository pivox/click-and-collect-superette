<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Shop;
use App\Processor\CustomerOrderWhatsappContactProcessor;
use App\Processor\MerchantOrderWhatsappContactProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Prepared WhatsApp contact for an order (S14-005 semi-manual flow).
 * Same output shape for the customer→merchant and merchant→customer directions;
 * each operation has its own processor enforcing ownership.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/me/orders/{orderId}/whatsapp-contact',
            uriVariables: [
                'orderId' => new Link(fromClass: OrderOutput::class, identifiers: ['id']),
            ],
            requirements: ['orderId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            input: false,
            read: false,
            normalizationContext: ['groups' => ['order_whatsapp:read']],
            processor: CustomerOrderWhatsappContactProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/whatsapp-contact',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: MerchantOrderDetailOutput::class, identifiers: ['id']),
            ],
            requirements: ['orderId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            input: false,
            read: false,
            normalizationContext: ['groups' => ['order_whatsapp:read']],
            processor: MerchantOrderWhatsappContactProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class OrderWhatsappContactOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['order_whatsapp:read'])]
        public string $id,
        #[Groups(['order_whatsapp:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['order_whatsapp:read'])]
        public string $phone,
        #[Groups(['order_whatsapp:read'])]
        public string $message,
        #[Groups(['order_whatsapp:read'])]
        #[SerializedName('whatsapp_url')]
        public string $whatsappUrl,
    ) {
    }
}
