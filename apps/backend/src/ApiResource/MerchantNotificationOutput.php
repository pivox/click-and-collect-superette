<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Processor\MerchantNotificationMarkReadProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/merchant/notifications/{id}/read',
            uriVariables: [
                'id' => new Link(fromClass: MerchantNotificationOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_notification:read']],
            input: false,
            status: 200,
            read: false,
            processor: MerchantNotificationMarkReadProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantNotificationOutput
{
    public function __construct(
        #[ApiProperty(identifier: false)]
        #[Groups(['merchant_notification:read'])]
        public string $id,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('order_id')]
        public ?string $orderId,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('title_fr')]
        public string $titleFr,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('title_ar')]
        public string $titleAr,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('body_fr')]
        public string $bodyFr,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('body_ar')]
        public string $bodyAr,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('is_read')]
        public bool $isRead,
        #[Groups(['merchant_notification:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
