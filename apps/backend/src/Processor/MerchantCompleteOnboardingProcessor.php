<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantOnboardingOutput;
use App\Entity\User;
use App\Service\MerchantOnboardingCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<null, MerchantOnboardingOutput>
 */
final readonly class MerchantCompleteOnboardingProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private MerchantOnboardingCalculator $calculator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantOnboardingOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        if (null === $merchant->getOnboardingCompletedAt()) {
            $merchant->setOnboardingCompletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->calculator->calculate($merchant);
    }
}
