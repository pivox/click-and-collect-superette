<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Processor\CustomerNotificationMarkReadProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/me/notifications/{id}/read',
            uriVariables: [
                'id' => new Link(fromClass: CustomerNotificationOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['customer_notification:read']],
            input: false,
            status: 200,
            read: false,
            processor: CustomerNotificationMarkReadProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerNotificationOutput
{
    public function __construct(
        #[ApiProperty(identifier: false)]
        #[Groups(['customer_notification:read'])]
        public string $id,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('order_id')]
        public ?string $orderId,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('title_fr')]
        public string $titleFr,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('title_ar')]
        public string $titleAr,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('body_fr')]
        public string $bodyFr,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('body_ar')]
        public string $bodyAr,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('is_read')]
        public bool $isRead,
        #[Groups(['customer_notification:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
