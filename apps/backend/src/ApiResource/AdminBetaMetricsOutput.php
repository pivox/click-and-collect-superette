<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Provider\AdminBetaMetricsProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/beta-metrics',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_beta_metrics:read']],
            provider: AdminBetaMetricsProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'date_from' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date'],
                    description: 'Début de période inclus, au format YYYY-MM-DD.',
                ),
                'date_to' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date'],
                    description: 'Fin de période incluse, au format YYYY-MM-DD.',
                ),
                'store_id' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'uuid'],
                    description: 'Filtre optionnel par supérette.',
                ),
            ],
        ),
    ],
)]
final readonly class AdminBetaMetricsOutput
{
    /**
     * @param list<AdminBetaMetricsStoreOutput> $stores
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('date_from')]
        public string $dateFrom,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('date_to')]
        public string $dateTo,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('store_id')]
        public ?string $storeId,
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
        #[SerializedName('active_stores')]
        public int $activeStores,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('activated_stores')]
        public int $activatedStores,
        #[Groups(['admin_beta_metrics:read'])]
        #[SerializedName('store_activation_rate')]
        public float $storeActivationRate,
        #[Groups(['admin_beta_metrics:read'])]
        public array $stores,
    ) {
    }
}
