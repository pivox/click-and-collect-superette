<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SubscriptionOutput;
use App\Entity\User;
use App\Service\SubscriptionResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<SubscriptionOutput>
 */
final readonly class MerchantSubscriptionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private SubscriptionResolver $subscriptionResolver,
        private SubscriptionOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SubscriptionOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        // MERCHANT-TEAM-002: organization subscription first, so every active
        // account of the organization sees the same single subscription.
        $subscription = $this->subscriptionResolver->forMerchantUser($merchant);
        if (null === $subscription) {
            throw new NotFoundHttpException('MERCHANT_SUBSCRIPTION_NOT_FOUND');
        }

        return $this->outputFactory->fromSubscription($subscription);
    }
}
