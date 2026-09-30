<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminPlatformSettingsOutput;
use App\Dto\AdminPlatformSettingsInput;
use App\Provider\AdminPlatformSettingsOutputFactory;
use App\Service\AdminAuditLogger;
use App\Service\FrontendOriginValidator;
use App\Service\PlatformSettingsManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<AdminPlatformSettingsInput, AdminPlatformSettingsOutput>
 */
final readonly class AdminUpdatePlatformSettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private PlatformSettingsManager $platformSettingsManager,
        private FrontendOriginValidator $frontendOriginValidator,
        private AdminPlatformSettingsOutputFactory $outputFactory,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminPlatformSettingsOutput
    {
        if (!$data instanceof AdminPlatformSettingsInput) {
            throw new \InvalidArgumentException('AdminPlatformSettingsInput expected.');
        }

        $setting = $this->platformSettingsManager->current();
        $setting->updateFrontendOrigin($this->frontendOriginValidator->normalize($data->frontendOrigin));

        $this->auditLogger->log(
            action: 'platform.settings_update',
            resourceType: 'platform_settings',
            resourceId: $setting->getId()->toRfc4122(),
            summary: 'Mise à jour des paramètres plateforme.',
            metadata: [
                'frontend_origin' => $setting->getFrontendOrigin(),
            ],
        );
        $this->entityManager->flush();

        return $this->outputFactory->fromSetting($setting);
    }
}
