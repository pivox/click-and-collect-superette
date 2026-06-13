<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminFeedbackSettingsOutput;
use App\Service\FeedbackSettingsManager;

/**
 * @implements ProviderInterface<AdminFeedbackSettingsOutput>
 */
final readonly class AdminFeedbackSettingsProvider implements ProviderInterface
{
    public function __construct(
        private FeedbackSettingsManager $feedbackSettingsManager,
        private AdminFeedbackSettingsOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminFeedbackSettingsOutput
    {
        return $this->outputFactory->fromSetting($this->feedbackSettingsManager->current());
    }
}
