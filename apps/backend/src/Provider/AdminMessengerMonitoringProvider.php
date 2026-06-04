<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminMessengerMonitoringOutput;
use App\Service\MessengerQueueMonitor;

/**
 * @implements ProviderInterface<AdminMessengerMonitoringOutput>
 */
final readonly class AdminMessengerMonitoringProvider implements ProviderInterface
{
    public function __construct(private MessengerQueueMonitor $monitor)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminMessengerMonitoringOutput
    {
        $snapshot = $this->monitor->snapshot();

        return new AdminMessengerMonitoringOutput(
            id: 'admin-messenger-monitoring',
            status: $snapshot->status,
            pending: $snapshot->pending,
            failed: $snapshot->failed,
            oldestAgeSeconds: $snapshot->oldestAgeSeconds,
            lastConsumedAt: $snapshot->lastConsumedAt?->format(\DateTimeInterface::ATOM),
            thresholds: [
                'pending' => $snapshot->pendingThreshold,
                'oldest_age_s' => $snapshot->oldestAgeSecondsThreshold,
            ],
            checkedAt: $snapshot->checkedAt->format(\DateTimeInterface::ATOM),
        );
    }
}
