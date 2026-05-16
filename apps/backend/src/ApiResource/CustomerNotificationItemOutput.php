<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Notification;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class CustomerNotificationItemOutput
{
    public function __construct(
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        public string $id,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        #[SerializedName('order_id')]
        public ?string $orderId,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        #[SerializedName('title_fr')]
        public string $titleFr,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        #[SerializedName('title_ar')]
        public string $titleAr,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        #[SerializedName('body_fr')]
        public string $bodyFr,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        #[SerializedName('body_ar')]
        public string $bodyAr,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
        #[SerializedName('is_read')]
        public bool $isRead,
        #[Groups(['customer_notification_list:read', 'customer_notification:read'])]
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
