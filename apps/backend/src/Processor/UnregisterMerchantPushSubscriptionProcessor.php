<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PushSubscriptionInput;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class UnregisterMerchantPushSubscriptionProcessor implements ProcessorInterface
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
            throw new AccessDeniedHttpException('Only authenticated merchants can unregister push subscriptions');
        }

        $hash = \hash('sha256', $data->endpoint);
        $subscription = $this->pushSubscriptionRepository->findByEndpointHash($hash);

        if ($subscription && $subscription->getUser()->getId()->equals($user->getId())) {
            $this->entityManager->remove($subscription);
            $this->entityManager->flush();
        }

        return null;
    }
}
