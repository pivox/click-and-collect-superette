<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminFeedbackSettingsOutput;
use App\Dto\AdminFeedbackSettingsInput;
use App\Provider\AdminFeedbackSettingsOutputFactory;
use App\Service\AdminAuditLogger;
use App\Service\FeedbackSettingsManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<AdminFeedbackSettingsInput, AdminFeedbackSettingsOutput>
 */
final readonly class AdminUpdateFeedbackSettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private FeedbackSettingsManager $feedbackSettingsManager,
        private AdminFeedbackSettingsOutputFactory $outputFactory,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminFeedbackSettingsOutput
    {
        if (!$data instanceof AdminFeedbackSettingsInput) {
            throw new \InvalidArgumentException('AdminFeedbackSettingsInput expected.');
        }

        $setting = $this->feedbackSettingsManager->current();
        $setting->update(
            globalEnabled: $data->globalEnabled,
            clientEnabled: $data->clientEnabled,
            merchantEnabled: $data->merchantEnabled,
            adminEnabled: $data->adminEnabled,
            clientAreaEnabled: $data->clientAreaEnabled,
            merchantAreaEnabled: $data->merchantAreaEnabled,
            adminAreaEnabled: $data->adminAreaEnabled,
            requireAuthenticatedUser: true,
        );

        $this->auditLogger->log(
            action: 'feedback.settings_update',
            resourceType: 'feedback_settings',
            resourceId: $setting->getId()->toRfc4122(),
            summary: 'Mise à jour des paramètres Feedback / Retour.',
            metadata: [
                'global_enabled' => $setting->isGlobalEnabled(),
                'client_enabled' => $setting->isClientEnabled(),
                'merchant_enabled' => $setting->isMerchantEnabled(),
                'admin_enabled' => $setting->isAdminEnabled(),
                'client_area_enabled' => $setting->isClientAreaEnabled(),
                'merchant_area_enabled' => $setting->isMerchantAreaEnabled(),
                'admin_area_enabled' => $setting->isAdminAreaEnabled(),
            ],
        );
        $this->entityManager->flush();

        return $this->outputFactory->fromSetting($setting);
    }
}
