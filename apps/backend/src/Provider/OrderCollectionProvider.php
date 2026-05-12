<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\OrderHistoryOutput;
use App\ApiResource\OrderOutput;
use App\Entity\Order;
use App\Entity\User;
use App\Factory\OrderOutputFactory;
use App\Repository\OrderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<OrderHistoryOutput>
 */
final readonly class OrderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private OrderOutputFactory $orderOutputFactory,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrderHistoryOutput
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $limit = min(50, max(1, (int) ($request?->query->get('limit') ?? 20)));
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepository->findByCustomerPaginated($user, $limit, $offset);
        $total = $this->orderRepository->countByCustomer($user);

        $items = array_map(
            fn (Order $order): OrderOutput => $this->orderOutputFactory->toOutput($order),
            $orders,
        );

        return new OrderHistoryOutput(
            id: 'current',
            items: $items,
            total: $total,
            page: $page,
            limit: $limit,
        );
    }
}
