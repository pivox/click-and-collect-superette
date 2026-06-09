<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Processor\ProcessorInterface;
use App\Dto\PushSubscriptionInput;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;
use App\Entity\User;

final readonly class UnregisterCustomerPushSubscriptionProcessor implements ProcessorInterface
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
            throw new AccessDeniedHttpException('Only authenticated customers can unregister push subscriptions');
        }

        $hash = \hash('sha256', $data->endpoint);
        $subscription = $this->pushSubscriptionRepository->findByEndpointHash($hash);

        if ($subscription) {
            $this->entityManager->remove($subscription);
            $this->entityManager->flush();
        }

        return null;
    }
}
