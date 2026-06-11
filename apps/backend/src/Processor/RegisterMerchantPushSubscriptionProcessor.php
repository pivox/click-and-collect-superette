<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PushSubscriptionInput;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProcessorInterface<PushSubscriptionInput, mixed> */
final readonly class RegisterMerchantPushSubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Only authenticated merchants can register push subscriptions');
        }

        $hash = hash('sha256', $data->endpoint);
        $existing = $this->pushSubscriptionRepository->findByEndpointHash($hash);

        if ($existing) {
            // Upsert: reassign to current user with fresh browser keys.
            $existing->refresh($user, $data->endpoint, $data->p256dhKey, $data->authKey, 'merchant', $data->userAgent);
            $this->entityManager->flush();
        } else {
            // Create new subscription
            $subscription = new PushSubscription(
                user: $user,
                endpoint: $data->endpoint,
                p256dhKey: $data->p256dhKey,
                authKey: $data->authKey,
                scope: 'merchant',
                userAgent: $data->userAgent,
            );
            $this->entityManager->persist($subscription);
            $this->entityManager->flush();
        }

        return null;
    }
}
