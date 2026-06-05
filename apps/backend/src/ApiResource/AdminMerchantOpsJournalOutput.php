<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantOpsJournalOutput
{
    public function __construct(
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('overdue_orders_count')]
        public int $overdueOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('cancelled_orders_count')]
        public int $cancelledOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('last_activity_at')]
        public ?string $lastActivityAt,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('last_activity_status')]
        public ?string $lastActivityStatus,
    ) {
    }
}
