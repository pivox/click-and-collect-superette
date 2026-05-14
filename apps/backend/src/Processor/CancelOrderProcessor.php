<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OrderOutput;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Factory\OrderOutputFactory;
use App\Repository\OrderRepository;
use App\Service\OrderStatusLogRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, OrderOutput>
 */
final readonly class CancelOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
        private OrderOutputFactory $orderOutputFactory,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderOutput
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

        if (OrderStatus::Submitted !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_SUBMITTED');
        }

        $order->cancel();
        $slot = $order->getPickupSlot();
        if (null !== $slot) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE pickup_slots SET booked_count = CASE WHEN booked_count > 0 THEN booked_count - 1 ELSE 0 END WHERE id = :id',
                ['id' => $slot->getId()->toBinary()],
            );
        }
        $this->orderStatusLogRecorder->record($order, OrderStatus::Cancelled);

        $this->entityManager->flush();

        return $this->orderOutputFactory->toOutput($order);
    }
}
