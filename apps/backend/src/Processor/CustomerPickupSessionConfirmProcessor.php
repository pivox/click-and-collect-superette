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
use Symfony\Bundle\SecurityBundle\Security;
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
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        // Ownership: 404 rather than 403 to avoid leaking session existence.
        /** @var User $user */
        $user = $this->security->getUser();
        $order = $pickupSession->getOrder();
        if (!$order->getCustomer()->getId()->equals($user->getId())) {
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        if ($pickupSession->isUsed() || OrderStatus::Completed === $order->getStatus()) {
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        if (null === $pickupSession->getScannedAt()) {
            throw new ConflictHttpException('PICKUP_SESSION_NOT_SCANNED');
        }

        // Order status is checked before expiry: a wrong status is a more fundamental
        // precondition than session TTL, and gives the client a more actionable error.
        if (OrderStatus::PickupPending !== $order->getStatus()) {
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
        } catch (\LogicException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

        $this->entityManager->flush();

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
