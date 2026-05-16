<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomerOrderPickupSessionStatus;
use App\ApiResource\CustomerOrderStatusOutput;
use App\Entity\PickupSession;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\PickupSessionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<CustomerOrderStatusOutput>
 */
final readonly class CustomerOrderStatusProvider implements ProviderInterface
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
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerOrderStatusOutput
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

        $pickupSession = $this->pickupSessionRepository->findOneByOrder($order);
        $sessionStatus = null !== $pickupSession
            ? $this->buildSessionStatus($pickupSession)
            : CustomerOrderPickupSessionStatus::none();

        $status = $order->getStatus();

        return new CustomerOrderStatusOutput(
            orderId: $order->getId()->toRfc4122(),
            status: $status->value,
            statusLabelFr: $status->labelFr(),
            statusLabelAr: $status->labelAr(),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            pickupSession: $sessionStatus,
        );
    }

    private function buildSessionStatus(PickupSession $session): CustomerOrderPickupSessionStatus
    {
        return new CustomerOrderPickupSessionStatus(
            exists: true,
            isScanned: null !== $session->getScannedAt(),
            merchantConfirmed: null !== $session->getMerchantConfirmedAt(),
            customerConfirmed: null !== $session->getCustomerConfirmedAt(),
            isUsed: $session->isUsed(),
            forceCompletedByMerchant: $session->isForceCompletedByMerchant(),
        );
    }
}
