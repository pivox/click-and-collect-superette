<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MobileConfigOutput;

/**
 * Serves the public mobile configuration from container parameters (#618).
 *
 * Fail-open by design: defaults mean "no version constraint" (0.0.0) and
 * "maintenance disabled", so a missing configuration never blocks the apps.
 * Semver comparison happens app-side (ADR-0007).
 *
 * @implements ProviderInterface<MobileConfigOutput>
 */
final readonly class MobileConfigProvider implements ProviderInterface
{
    public function __construct(
        private string $mobileMinVersionClientAndroid,
        private string $mobileMinVersionClientIos,
        private string $mobileMinVersionMerchantAndroid,
        private string $mobileMinVersionMerchantIos,
        private bool $mobileMaintenanceEnabled,
        private string $mobileMaintenanceMessageFr,
        private string $mobileMaintenanceMessageAr,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MobileConfigOutput
    {
        return new MobileConfigOutput(
            id: 'mobile-config',
            minimumAppVersion: [
                'client' => [
                    'android' => $this->mobileMinVersionClientAndroid,
                    'ios' => $this->mobileMinVersionClientIos,
                ],
                'merchant' => [
                    'android' => $this->mobileMinVersionMerchantAndroid,
                    'ios' => $this->mobileMinVersionMerchantIos,
                ],
            ],
            maintenance: [
                'enabled' => $this->mobileMaintenanceEnabled,
                'message_fr' => '' === $this->mobileMaintenanceMessageFr ? null : $this->mobileMaintenanceMessageFr,
                'message_ar' => '' === $this->mobileMaintenanceMessageAr ? null : $this->mobileMaintenanceMessageAr,
            ],
        );
    }
}
