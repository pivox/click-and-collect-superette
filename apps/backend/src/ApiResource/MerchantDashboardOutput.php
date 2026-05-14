<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Shop;
use App\Provider\MerchantDashboardProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/dashboard/today',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_dashboard:read']],
            provider: MerchantDashboardProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantDashboardOutput
{
    /**
     * @param array<string, int>                      $ordersByStatus
     * @param list<MerchantDashboardPickupSlotOutput> $pickupSlotsToday
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_dashboard:read'])]
        public string $date,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('total_orders_today')]
        public int $totalOrdersToday,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('orders_by_status')]
        public array $ordersByStatus,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('submitted_count')]
        public int $submittedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('accepted_count')]
        public int $acceptedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('partially_accepted_count')]
        public int $partiallyAcceptedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('preparing_count')]
        public int $preparingCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('ready_count')]
        public int $readyCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('cancelled_count')]
        public int $cancelledCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('rejected_count')]
        public int $rejectedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('completed_count')]
        public int $completedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('urgent_submitted_count')]
        public int $urgentSubmittedCount,
        #[Groups(['merchant_dashboard:read'])]
        #[SerializedName('pickup_slots_today')]
        public array $pickupSlotsToday,
    ) {
    }
}
