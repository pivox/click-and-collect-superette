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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
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
            $this->logger->warning('pickup.confirm_merchant.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_FOUND',
                'pickup_session_id' => $pickupSessionId,
            ]);
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $order = $pickupSession->getOrder();
        $orderId = $order->getId()->toRfc4122();
        $storeId = $order->getShop()->getId()->toRfc4122();

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($order->getShop());

        $this->logger->debug('pickup.confirm_merchant.start', [
            'pickup_session_id' => $pickupSessionId,
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        // A consumed session or completed order must not be reclassified as a scan/state error.
        if ($pickupSession->isUsed() || OrderStatus::Completed === $order->getStatus()) {
            $this->logger->warning('pickup.confirm_merchant.rejected', [
                'reason' => 'PICKUP_SESSION_ALREADY_USED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        if (null === $pickupSession->getScannedAt()) {
            $this->logger->warning('pickup.confirm_merchant.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_SCANNED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_NOT_SCANNED');
        }

        if ($pickupSession->isExpired()) {
            $this->logger->warning('pickup.confirm_merchant.rejected', [
                'reason' => 'PICKUP_SESSION_EXPIRED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_EXPIRED');
        }

        if (OrderStatus::PickupPending !== $order->getStatus()) {
            $this->logger->warning('pickup.confirm_merchant.rejected', [
                'reason' => 'ORDER_NOT_PICKUP_PENDING',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('ORDER_NOT_PICKUP_PENDING');
        }

        try {
            $pickupSession->confirmByMerchant();
            if ($pickupSession->isUsed()) {
                $this->orderTransitionService->markCompleted($order);
            }
            $this->entityManager->flush();
            $this->logger->info('pickup.confirm_merchant.done', [
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
                'completed' => $pickupSession->isUsed(),
            ]);
        } catch (\LogicException $e) {
            $this->logger->warning('pickup.confirm_merchant.rejected', [
                'reason' => $e->getMessage(),
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('pickup.confirm_merchant.failed', [
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

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
