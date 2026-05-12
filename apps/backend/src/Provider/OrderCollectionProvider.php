<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OrderOutput;
use App\Entity\Order;
use App\Entity\User;
use App\Factory\OrderOutputFactory;
use App\Repository\OrderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<OrderOutput>
 */
final readonly class OrderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderOutputFactory $orderOutputFactory,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<OrderOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        return array_map(
            fn (Order $order): OrderOutput => $this->orderOutputFactory->toOutput($order),
            $this->orderRepository->findByCustomer($user),
        );
    }
}
