<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminPlatformSettingsOutput;
use App\Service\PlatformSettingsManager;

/**
 * @implements ProviderInterface<AdminPlatformSettingsOutput>
 */
final readonly class AdminPlatformSettingsProvider implements ProviderInterface
{
    public function __construct(
        private PlatformSettingsManager $platformSettingsManager,
        private AdminPlatformSettingsOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminPlatformSettingsOutput
    {
        return $this->outputFactory->fromSetting($this->platformSettingsManager->current());
    }
}
