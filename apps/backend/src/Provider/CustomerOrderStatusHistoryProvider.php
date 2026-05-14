<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OrderStatusHistoryOutput;
use App\ApiResource\OrderStatusTransitionOutput;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\OrderStatusLogRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<OrderStatusHistoryOutput>
 */
final readonly class CustomerOrderStatusHistoryProvider implements ProviderInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderStatusLogRepository $orderStatusLogRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrderStatusHistoryOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneByCustomerAndId($user, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        return new OrderStatusHistoryOutput(
            orderId: $order->getId()->toRfc4122(),
            transitions: array_map(
                static fn ($log): OrderStatusTransitionOutput => new OrderStatusTransitionOutput(
                    status: $log->getStatus()->value,
                    note: $log->getNote(),
                    at: $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ),
                $this->orderStatusLogRepository->findChronologicalForOrder($order),
            ),
        );
    }
}
