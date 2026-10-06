<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AiBudgetPolicyOutput;
use App\Dto\AiBudgetPolicyWriteInput;
use App\Exception\AiBudgetPolicyUnavailableException;
use App\Mapper\AiBudgetPolicyMapper;
use App\Repository\AiBudgetPolicyRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<AiBudgetPolicyWriteInput, AiBudgetPolicyOutput>
 */
final readonly class UpdateAiBudgetPolicyProcessor implements ProcessorInterface
{
    public function __construct(
        private AiBudgetPolicyRepository $aiBudgetPolicyRepository,
        private AiBudgetPolicyMapper $aiBudgetPolicyMapper,
        private EntityManagerInterface $entityManager,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AiBudgetPolicyOutput
    {
        if (!$data instanceof AiBudgetPolicyWriteInput) {
            throw new \InvalidArgumentException('AiBudgetPolicyWriteInput expected.');
        }

        $policy = $this->aiBudgetPolicyRepository->findDefault();
        if (null === $policy) {
            throw new AiBudgetPolicyUnavailableException();
        }

        $this->aiBudgetPolicyMapper->applyWriteInput($policy, $data);

        $this->auditLogger->log(
            action: 'ai_budget_policy.update',
            resourceType: 'AiBudgetPolicy',
            resourceId: $policy->getId()->toRfc4122(),
            summary: $data->enabled
                ? 'Garde-fou IA activé'
                : 'Garde-fou IA désactivé — aucun appel payant ne peut démarrer',
            metadata: [
                'enabled' => $data->enabled,
                'openai_enabled' => $data->openaiEnabled,
                'gemini_enabled' => $data->geminiEnabled,
                'mistral_enabled' => $data->mistralEnabled,
                'qwen_enabled' => $data->qwenEnabled,
                'currency' => $data->currency,
                'global_period_cap_amount' => $data->globalPeriodCapAmount,
            ],
        );

        $this->entityManager->flush();

        return $this->aiBudgetPolicyMapper->toOutput($policy);
    }
}
