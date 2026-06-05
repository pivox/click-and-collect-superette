<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class SubscriptionPaymentListOutput
{
    /**
     * @param list<SubscriptionPaymentOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['subscription_payment_list:read'])]
        public array $items,
        #[Groups(['subscription_payment_list:read'])]
        public int $page,
        #[Groups(['subscription_payment_list:read'])]
        public int $limit,
        #[Groups(['subscription_payment_list:read'])]
        public int $total,
    ) {
    }
}
