<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Processor\ProcessorInterface;
use App\Dto\PushSubscriptionInput;
use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;
use App\Entity\User;

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

        $hash = \hash('sha256', $data->endpoint);
        $existing = $this->pushSubscriptionRepository->findByEndpointHash($hash);

        if ($existing) {
            // Upsert: reassign to current user with new keys
            $existing->setUser($user);
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
