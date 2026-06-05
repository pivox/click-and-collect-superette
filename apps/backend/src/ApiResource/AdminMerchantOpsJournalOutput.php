<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantOpsJournalOutput
{
    public function __construct(
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('received_orders_count')]
        public int $receivedOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('accepted_orders_count')]
        public int $acceptedOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('rejected_orders_count')]
        public int $rejectedOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('average_response_minutes')]
        public ?int $averageResponseMinutes,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('overdue_orders_count')]
        public int $overdueOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('cancelled_orders_count')]
        public int $cancelledOrdersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('incidents_count')]
        public int $incidentsCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('open_incidents_count')]
        public int $openIncidentsCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('payment_reminders_count')]
        public int $paymentRemindersCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('admin_actions_count')]
        public int $adminActionsCount,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('health_status')]
        public string $healthStatus,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('last_activity_at')]
        public ?string $lastActivityAt,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('last_activity_status')]
        public ?string $lastActivityStatus,
    ) {
    }
}
