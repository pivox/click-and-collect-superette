<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminBetaMetricsStoreOutput
{
    public function __construct(
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('store_name')]
        public string $storeName,
        #[Groups(['admin_beta_metrics:read'])]
        public int $submitted,
        #[Groups(['admin_beta_metrics:read'])]
        public int $accepted,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('partially_accepted')]
        public int $partiallyAccepted,
        #[Groups(['admin_beta_metrics:read'])]
        public int $rejected,
        #[Groups(['admin_beta_metrics:read'])]
        public int $cancelled,
        #[Groups(['admin_beta_metrics:read'])]
        public int $completed,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('acceptance_rate')]
        public float $acceptanceRate,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('cancellation_rate')]
        public float $cancellationRate,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('last_activity_at')]
        public ?string $lastActivityAt,
    ) {
    }
}
