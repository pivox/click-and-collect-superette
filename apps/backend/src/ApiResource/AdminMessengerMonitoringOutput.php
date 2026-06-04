<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\AdminMessengerMonitoringProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/ops/messenger',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_messenger_monitoring:read']],
            provider: AdminMessengerMonitoringProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AdminMessengerMonitoringOutput
{
    /**
     * @param array{pending: int, oldest_age_s: int} $thresholds
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_messenger_monitoring:read'])]
        public string $status,
        #[Groups(['admin_messenger_monitoring:read'])]
        public int $pending,
        #[Groups(['admin_messenger_monitoring:read'])]
        public int $failed,
        #[Groups(['admin_messenger_monitoring:read'])]
        #[SerializedName('oldest_age_s')]
        public ?int $oldestAgeSeconds,
        #[Groups(['admin_messenger_monitoring:read'])]
        #[SerializedName('last_consumed_at')]
        public ?string $lastConsumedAt,
        #[Groups(['admin_messenger_monitoring:read'])]
        public array $thresholds,
        #[Groups(['admin_messenger_monitoring:read'])]
        #[SerializedName('checked_at')]
        public string $checkedAt,
    ) {
    }
}
