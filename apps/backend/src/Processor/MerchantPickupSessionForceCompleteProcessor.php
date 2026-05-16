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
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $order = $pickupSession->getOrder();
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($order->getShop());

        if (OrderStatus::Completed === $order->getStatus()) {
            throw new ConflictHttpException('ORDER_ALREADY_COMPLETED');
        }

        if ($pickupSession->isUsed()) {
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        if (null === $pickupSession->getScannedAt()) {
            throw new ConflictHttpException('PICKUP_SESSION_NOT_SCANNED');
        }

        // Expiry is intentionally NOT checked here: expiry guards the scan step only.
        // Once the order is pickup_pending, both parties must be able to finalise the
        // handoff even if the session TTL elapses between scan and force completion.

        if (OrderStatus::PickupPending !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_PICKUP_PENDING');
        }

        if (null !== $pickupSession->getCustomerConfirmedAt()) {
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_CUSTOMER_CONFIRMED');
        }

        if (null === $pickupSession->getMerchantConfirmedAt()) {
            throw new ConflictHttpException('PICKUP_SESSION_NOT_MERCHANT_CONFIRMED');
        }

        $scannedAt = $pickupSession->getScannedAt();
        $now = new \DateTimeImmutable();
        if ($now->getTimestamp() - $scannedAt->getTimestamp() < self::FORCE_COMPLETE_DELAY_SECONDS) {
            throw new ConflictHttpException('PICKUP_FORCE_COMPLETION_TOO_EARLY');
        }

        $note = trim($data->note);

        try {
            $pickupSession->forceCompleteByMerchant($note);
            $this->orderTransitionService->markCompleted($order, 'Force completion by merchant: '.$note);
        } catch (\LogicException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

        $this->entityManager->flush();

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
