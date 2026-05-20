<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantOnboardingOutput;
use App\Entity\User;
use App\Service\MerchantOnboardingCalculator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<MerchantOnboardingOutput>
 */
final readonly class MerchantOnboardingProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private MerchantOnboardingCalculator $calculator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantOnboardingOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        return $this->calculator->calculate($merchant);
    }
}
