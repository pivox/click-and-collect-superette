<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerPickupSessionConfirmOutput;
use App\Entity\PickupSession;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\PickupSessionRepository;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, CustomerPickupSessionConfirmOutput>
 */
final readonly class CustomerPickupSessionConfirmProcessor implements ProcessorInterface
{
    public function __construct(
        private PickupSessionRepository $pickupSessionRepository,
        private Security $security,
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerPickupSessionConfirmOutput
    {
        $pickupSessionId = (string) ($uriVariables['id'] ?? '');
        $pickupSession = $this->pickupSessionRepository->findOneByIdWithOrder($pickupSessionId);
        if (null === $pickupSession) {
            $this->logger->warning('pickup.confirm_customer.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_FOUND',
                'pickup_session_id' => $pickupSessionId,
            ]);
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        // Ownership: 404 rather than 403 to avoid leaking session existence.
        /** @var User $user */
        $user = $this->security->getUser();
        $order = $pickupSession->getOrder();
        $userId = $user->getId()->toRfc4122();
        $orderId = $order->getId()->toRfc4122();

        if (!$order->getCustomer()->getId()->equals($user->getId())) {
            $this->logger->warning('pickup.confirm_customer.rejected', [
                'reason' => 'ownership_mismatch',
                'pickup_session_id' => $pickupSessionId,
                'user_id' => $userId,
            ]);
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $storeId = $order->getShop()->getId()->toRfc4122();

        $this->logger->debug('pickup.confirm_customer.start', [
            'pickup_session_id' => $pickupSessionId,
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        if ($pickupSession->isUsed() || OrderStatus::Completed === $order->getStatus()) {
            $this->logger->warning('pickup.confirm_customer.rejected', [
                'reason' => 'PICKUP_SESSION_ALREADY_USED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        if (null === $pickupSession->getScannedAt()) {
            $this->logger->warning('pickup.confirm_customer.rejected', [
                'reason' => 'PICKUP_SESSION_NOT_SCANNED',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('PICKUP_SESSION_NOT_SCANNED');
        }

        // Order status is checked before expiry: a wrong status is a more fundamental
        // precondition than session TTL, and gives the client a more actionable error.
        if (OrderStatus::PickupPending !== $order->getStatus()) {
            $this->logger->warning('pickup.confirm_customer.rejected', [
                'reason' => 'ORDER_NOT_PICKUP_PENDING',
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException('ORDER_NOT_PICKUP_PENDING');
        }

        // Expiry is intentionally NOT checked here. Expiry guards the scan step
        // (preventing stale QR codes from initiating a pickup). Once the QR has been
        // scanned and the order is pickup_pending, both parties must be able to finish
        // the handoff even if the session TTL elapses between scan and confirm.
        // Known limitation: two simultaneous confirm requests are not serialised with
        // a database-level lock (SELECT FOR UPDATE). markCompleted() is idempotent on
        // the order status, but OrderStatusLog may receive a duplicate entry under
        // very high concurrency. Tracked for resolution in a future sprint.

        try {
            $pickupSession->confirmByCustomer();
            if ($pickupSession->isUsed()) {
                $this->orderTransitionService->markCompleted($order);
            }
            $this->entityManager->flush();
            $this->logger->info('pickup.confirm_customer.done', [
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
                'completed' => $pickupSession->isUsed(),
            ]);
        } catch (\LogicException $e) {
            $this->logger->warning('pickup.confirm_customer.rejected', [
                'reason' => $e->getMessage(),
                'pickup_session_id' => $pickupSessionId,
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
            throw new ConflictHttpException($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('pickup.confirm_customer.failed', [
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

    private function toOutput(PickupSession $pickupSession): CustomerPickupSessionConfirmOutput
    {
        $order = $pickupSession->getOrder();
        $scannedAt = $pickupSession->getScannedAt();
        \assert(null !== $scannedAt, 'scannedAt must be set after guard check');

        return new CustomerPickupSessionConfirmOutput(
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
