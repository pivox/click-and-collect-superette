<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AiBudgetPolicyOutput;
use App\Exception\AiBudgetPolicyUnavailableException;
use App\Mapper\AiBudgetPolicyMapper;
use App\Repository\AiBudgetPolicyRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @implements ProviderInterface<AiBudgetPolicyOutput>
 */
final readonly class AiBudgetPolicyProvider implements ProviderInterface
{
    public function __construct(
        private AiBudgetPolicyRepository $aiBudgetPolicyRepository,
        private AiBudgetPolicyMapper $aiBudgetPolicyMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AiBudgetPolicyOutput
    {
        $policy = $this->aiBudgetPolicyRepository->findDefault();
        if (null === $policy) {
            throw new HttpException(500, (new AiBudgetPolicyUnavailableException())->getMessage());
        }

        return $this->aiBudgetPolicyMapper->toOutput($policy);
    }
}
