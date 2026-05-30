<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantPickupSessionForceCompleteOutput;
use App\Dto\MerchantPickupSessionForceCompleteInput;
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
 * @implements ProcessorInterface<MerchantPickupSessionForceCompleteInput, MerchantPickupSessionForceCompleteOutput>
 */
final readonly class MerchantPickupSessionForceCompleteProcessor implements ProcessorInterface
{
    private const FORCE_COMPLETE_DELAY_SECONDS = 300; // 5 minutes

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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantPickupSessionForceCompleteOutput
    {
        \assert($data instanceof MerchantPickupSessionForceCompleteInput);

        $pickupSessionId = (string) ($uriVariables['id'] ?? '');
        $pickupSession = $this->pickupSessionRepository->findOneByIdWithOrder($pickupSessionId);
        if (null === $pickupSession) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_FOUND',
                'pickup_session_id' => $pickupSessionId,
            ]);
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $order = $pickupSession->getOrder();
        $orderId = $order->getId()->toRfc4122();
        $storeId = $order->getShop()->getId()->toRfc4122();

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($order->getShop());

        $this->logger->debug('pickup.force_complete.start', [
            'pickup_session_id' => $pickupSessionId,
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        if (OrderStatus::Completed === $order->getStatus()) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'ORDER_ALREADY_COMPLETED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('ORDER_ALREADY_COMPLETED');
        }

        if ($pickupSession->isUsed()) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'PICKUP_SESSION_ALREADY_USED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        if (null === $pickupSession->getScannedAt()) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_SCANNED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_NOT_SCANNED');
        }

        // Expiry is intentionally NOT checked here: expiry guards the scan step only.
        // Once the order is pickup_pending, both parties must be able to finalise the
        // handoff even if the session TTL elapses between scan and force completion.

        if (OrderStatus::PickupPending !== $order->getStatus()) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'ORDER_NOT_PICKUP_PENDING',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('ORDER_NOT_PICKUP_PENDING');
        }

        if (null !== $pickupSession->getCustomerConfirmedAt()) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'PICKUP_SESSION_ALREADY_CUSTOMER_CONFIRMED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_CUSTOMER_CONFIRMED');
        }

        if (null === $pickupSession->getMerchantConfirmedAt()) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_MERCHANT_CONFIRMED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_NOT_MERCHANT_CONFIRMED');
        }

        $scannedAt = $pickupSession->getScannedAt();
        $now = new \DateTimeImmutable();
        if ($now->getTimestamp() - $scannedAt->getTimestamp() < self::FORCE_COMPLETE_DELAY_SECONDS) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => 'PICKUP_FORCE_COMPLETION_TOO_EARLY',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_FORCE_COMPLETION_TOO_EARLY');
        }

        $note = trim($data->note);

        try {
            $pickupSession->forceCompleteByMerchant($note);
            $this->orderTransitionService->markCompleted($order, 'Force completion by merchant: '.$note);
            $this->entityManager->flush();
            $this->logger->info('pickup.force_completed', [
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
                'has_note' => '' !== $note,
            ]);
        } catch (\LogicException $e) {
            $this->logger->warning('pickup.force_complete.rejected', [
                'reason' => $e->getMessage(),
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('pickup.force_complete.failed', [
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

    private function toOutput(PickupSession $pickupSession): MerchantPickupSessionForceCompleteOutput
    {
        $order = $pickupSession->getOrder();
        $scannedAt = $pickupSession->getScannedAt();
        \assert(null !== $scannedAt, 'scannedAt must be set after guard check');

        return new MerchantPickupSessionForceCompleteOutput(
            id: $pickupSession->getId()->toRfc4122(),
            orderId: $order->getId()->toRfc4122(),
            orderStatus: $order->getStatus()->value,
            scannedAt: $scannedAt->format(\DateTimeInterface::ATOM),
            merchantConfirmedAt: $pickupSession->getMerchantConfirmedAt()?->format(\DateTimeInterface::ATOM),
            customerConfirmedAt: $pickupSession->getCustomerConfirmedAt()?->format(\DateTimeInterface::ATOM),
            isUsed: $pickupSession->isUsed(),
            isCompleted: OrderStatus::Completed === $order->getStatus(),
            forceCompletedByMerchant: $pickupSession->isForceCompletedByMerchant(),
            forceNote: $pickupSession->getForceNote(),
        );
    }
}
