<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\SubscriptionPaymentOutput;
use App\Dto\AdminSubscriptionPaymentCreateInput;
use App\Entity\User;
use App\Provider\SubscriptionPaymentOutputFactory;
use App\Service\SubscriptionPaymentRecorder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<AdminSubscriptionPaymentCreateInput, SubscriptionPaymentOutput>
 */
final readonly class AdminCreateSubscriptionPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private SubscriptionPaymentRecorder $recorder,
        private Security $security,
        private SubscriptionPaymentOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubscriptionPaymentOutput
    {
        if (!$data instanceof AdminSubscriptionPaymentCreateInput) {
            throw new \InvalidArgumentException('AdminSubscriptionPaymentCreateInput expected.');
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException('ADMIN_ACCESS_REQUIRED');
        }

        return $this->outputFactory->fromSubscriptionPayment($this->recorder->record($data, $admin));
    }
}
