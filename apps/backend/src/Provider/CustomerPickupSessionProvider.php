<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PickupSessionOutput;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\PickupSessionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<PickupSessionOutput>
 */
final readonly class CustomerPickupSessionProvider implements ProviderInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PickupSessionRepository $pickupSessionRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PickupSessionOutput
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneByCustomerAndId($user, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        if (!\in_array($order->getStatus(), [OrderStatus::Ready, OrderStatus::PickupPending], true)) {
            throw new ConflictHttpException('ORDER_NOT_READY');
        }

        $pickupSession = $this->pickupSessionRepository->findOneByOrder($order);
        if (null === $pickupSession) {
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $token = $pickupSession->getToken()->toRfc4122();

        return new PickupSessionOutput(
            id: $pickupSession->getId()->toRfc4122(),
            token: $token,
            expiresAt: $pickupSession->getExpiresAt()->format(\DateTimeInterface::ATOM),
            isUsed: $pickupSession->isUsed(),
            isExpired: $pickupSession->isExpired(),
            qrPayload: $token,
        );
    }
}
