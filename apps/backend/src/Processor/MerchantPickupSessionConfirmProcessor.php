<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantPickupSessionConfirmOutput;
use App\Entity\PickupSession;
use App\Enum\OrderStatus;
use App\Repository\PickupSessionRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, MerchantPickupSessionConfirmOutput>
 */
final readonly class MerchantPickupSessionConfirmProcessor implements ProcessorInterface
{
    public function __construct(
        private PickupSessionRepository $pickupSessionRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private OrderTransitionService $orderTransitionService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantPickupSessionConfirmOutput
    {
        $pickupSessionId = (string) ($uriVariables['id'] ?? '');
        $pickupSession = $this->pickupSessionRepository->findOneByIdWithOrder($pickupSessionId);
        if (null === $pickupSession) {
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $order = $pickupSession->getOrder();
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($order->getShop());

        // A consumed session or completed order must not be reclassified as a scan/state error.
        if ($pickupSession->isUsed() || OrderStatus::Completed === $order->getStatus()) {
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        if (null === $pickupSession->getScannedAt()) {
            throw new ConflictHttpException('PICKUP_SESSION_NOT_SCANNED');
        }

        if ($pickupSession->isExpired()) {
            throw new ConflictHttpException('PICKUP_SESSION_EXPIRED');
        }

        if (OrderStatus::PickupPending !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_PICKUP_PENDING');
        }

        try {
            $pickupSession->confirmByMerchant();
            if ($pickupSession->isUsed()) {
                $this->orderTransitionService->markCompleted($order);
            }
        } catch (\LogicException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

        $this->entityManager->flush();

        return $this->toOutput($pickupSession);
    }

    private function toOutput(PickupSession $pickupSession): MerchantPickupSessionConfirmOutput
    {
        $order = $pickupSession->getOrder();
        $scannedAt = $pickupSession->getScannedAt();
        if (null === $scannedAt) {
            throw new \LogicException('PICKUP_SESSION_NOT_SCANNED');
        }

        return new MerchantPickupSessionConfirmOutput(
            id: $pickupSession->getId()->toRfc4122(),
            orderId: $order->getId()->toRfc4122(),
            orderStatus: $order->getStatus()->value,
            scannedAt: $scannedAt->format(\DateTimeInterface::ATOM),
            merchantConfirmedAt: $pickupSession->getMerchantConfirmedAt()?->format(\DateTimeInterface::ATOM),
            customerConfirmedAt: $pickupSession->getCustomerConfirmedAt()?->format(\DateTimeInterface::ATOM),
            isUsed: $pickupSession->isUsed(),
            isCompleted: OrderStatus::Completed === $order->getStatus(),
        );
    }
}
