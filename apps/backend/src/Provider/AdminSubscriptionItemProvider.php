<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SubscriptionOutput;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<SubscriptionOutput>
 */
final readonly class AdminSubscriptionItemProvider implements ProviderInterface
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private SubscriptionOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SubscriptionOutput
    {
        $subscriptionId = (string) ($uriVariables['subscriptionId'] ?? '');
        if (!Uuid::isValid($subscriptionId)) {
            throw new NotFoundHttpException('ADMIN_SUBSCRIPTION_NOT_FOUND');
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);
        if (null === $subscription) {
            throw new NotFoundHttpException('ADMIN_SUBSCRIPTION_NOT_FOUND');
        }

        return $this->outputFactory->fromSubscription($subscription);
    }
}
