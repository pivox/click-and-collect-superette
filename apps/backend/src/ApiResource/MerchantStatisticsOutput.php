<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Shop;
use App\Provider\MerchantStatisticsProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/statistics',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_statistics:read']],
            provider: MerchantStatisticsProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
            parameters: [
                'date_from' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date'],
                    description: 'Début de période inclus, au format YYYY-MM-DD.',
                ),
                'date_to' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date'],
                    description: 'Fin de période incluse, au format YYYY-MM-DD.',
                ),
            ],
        ),
    ],
)]
final readonly class MerchantStatisticsOutput
{
    /**
     * @param list<MerchantStatisticsTopProductOutput> $topProducts
     * @param list<MerchantStatisticsTopSlotOutput>    $topSlots
     * @param array<string, int>                       $ordersByStatus
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('date_from')]
        public string $dateFrom,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('date_to')]
        public string $dateTo,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('total_orders')]
        public int $totalOrders,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('total_revenue_tnd')]
        public string $totalRevenueTnd,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('acceptance_rate')]
        public float $acceptanceRate,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('cancellation_rate')]
        public float $cancellationRate,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('rejection_rate')]
        public float $rejectionRate,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('orders_by_status')]
        public array $ordersByStatus,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('top_products')]
        public array $topProducts,
        #[Groups(['merchant_statistics:read'])]
        #[SerializedName('top_slots')]
        public array $topSlots,
    ) {
    }
}
