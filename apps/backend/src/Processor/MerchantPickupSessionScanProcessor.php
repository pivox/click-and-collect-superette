<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantPickupSessionScanOutput;
use App\Dto\MerchantPickupSessionScanInput;
use App\Entity\OrderLine;
use App\Entity\PickupSession;
use App\Enum\OrderStatus;
use App\Repository\PickupSessionRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantPickupSessionScanInput, MerchantPickupSessionScanOutput>
 */
final readonly class MerchantPickupSessionScanProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantPickupSessionScanOutput
    {
        if (!$data instanceof MerchantPickupSessionScanInput) {
            throw new \InvalidArgumentException('MerchantPickupSessionScanInput expected.');
        }

        $pickupSession = $this->pickupSessionRepository->findOneByToken(Uuid::fromString($data->token));
        if (null === $pickupSession) {
            throw new NotFoundHttpException('PICKUP_SESSION_NOT_FOUND');
        }

        $order = $pickupSession->getOrder();
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($order->getShop());

        // A session that was consumed cannot retroactively become an expired scan.
        if ($pickupSession->isUsed() || OrderStatus::Completed === $order->getStatus()) {
            throw new ConflictHttpException('PICKUP_SESSION_ALREADY_USED');
        }

        $now = new \DateTimeImmutable();
        if ($pickupSession->isExpired($now)) {
            throw new ConflictHttpException('PICKUP_SESSION_EXPIRED');
        }

        if (OrderStatus::PickupPending === $order->getStatus() && null !== $pickupSession->getScannedAt()) {
            return $this->toOutput($pickupSession);
        }

        if (OrderStatus::Ready !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_READY');
        }

        try {
            $pickupSession->scan($now);
            $this->orderTransitionService->markPickupPending($order);
        } catch (\LogicException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

        $this->entityManager->flush();

        return $this->toOutput($pickupSession);
    }

    private function toOutput(PickupSession $pickupSession): MerchantPickupSessionScanOutput
    {
        $order = $pickupSession->getOrder();
        $customer = $order->getCustomer();
        $scannedAt = $pickupSession->getScannedAt();
        if (null === $scannedAt) {
            throw new \LogicException('PICKUP_SESSION_NOT_SCANNED');
        }

        return new MerchantPickupSessionScanOutput(
            id: $pickupSession->getId()->toRfc4122(),
            orderId: $order->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            orderNumber: null,
            status: $order->getStatus()->value,
            scannedAt: $scannedAt->format(\DateTimeInterface::ATOM),
            customer: [
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
            ],
            lines: array_map(
                static fn (OrderLine $line): array => [
                    'merchant_product_id' => $line->getMerchantProduct()->getId()->toRfc4122(),
                    'name' => $line->getMerchantProduct()->getProductReference()->getNameFr(),
                    'quantity' => $line->getQuantity(),
                    'unit_price_tnd' => $line->getUnitPriceTnd(),
                ],
                $order->getLines()->toArray(),
            ),
        );
    }
}
