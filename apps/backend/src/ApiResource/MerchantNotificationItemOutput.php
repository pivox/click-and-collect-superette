<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Notification;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantNotificationItemOutput
{
    public function __construct(
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        public string $id,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('order_id')]
        public ?string $orderId,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('title_fr')]
        public string $titleFr,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('title_ar')]
        public string $titleAr,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('body_fr')]
        public string $bodyFr,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('body_ar')]
        public string $bodyAr,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('is_read')]
        public bool $isRead,
        #[Groups(['merchant_notification_list:read', 'merchant_notification:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Notification $notification): self
    {
        return new self(
            id: $notification->getId()->toRfc4122(),
            orderId: $notification->getOrder()?->getId()->toRfc4122(),
            titleFr: $notification->getTitleFr(),
            titleAr: $notification->getTitleAr(),
            bodyFr: $notification->getBodyFr(),
            bodyAr: $notification->getBodyAr(),
            isRead: $notification->isRead(),
            createdAt: $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
