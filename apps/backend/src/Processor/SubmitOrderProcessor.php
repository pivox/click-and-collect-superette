<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OrderOutput;
use App\Dto\SubmitOrderInput;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Enum\OrderStatus;
use App\Factory\OrderOutputFactory;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\KadhiaRepository;
use App\Repository\OrderRepository;
use App\Repository\PickupSlotRepository;
use App\Service\MerchantResponseTimeoutScheduler;
use App\Service\NotificationService;
use App\Service\OrderNumberGenerator;
use App\Service\OrderStatusLogRecorder;
use App\Service\PickupSlotDisplayTime;
use App\Service\PickupSlotDuration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SubmitOrderInput, OrderOutput>
 */
final readonly class SubmitOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private PickupSlotRepository $pickupSlotRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private KadhiaRepository $kadhiaRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
        private OrderNumberGenerator $orderNumberGenerator,
        private OrderOutputFactory $orderOutputFactory,
        private Security $security,
        private NotificationService $notificationService,
        private MerchantResponseTimeoutScheduler $merchantResponseTimeoutScheduler,
        private ClockInterface $clock,
        private int $partialAcceptanceExpirationLeadSeconds,
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderOutput
    {
        if (!$data instanceof SubmitOrderInput) {
            throw new \InvalidArgumentException('SubmitOrderInput expected.');
        }

        $kadhiaId = (string) ($uriVariables['kadhiaId'] ?? '');
        $slotId = $data->pickupSlotId;

        $this->logger->debug('order.submit.start', [
            'kadhia_id' => $kadhiaId,
            'slot_id' => $slotId,
        ]);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $this->logRejected('CUSTOMER_ACCESS_REQUIRED', $kadhiaId, $slotId);
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $userId = $user->getId()->toRfc4122();

        if (!Uuid::isValid($kadhiaId)) {
            $this->logRejected('KADHIA_NOT_FOUND', $kadhiaId, $slotId, $userId);
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        $kadhia = $this->kadhiaRepository->findByIdAndCustomer($kadhiaId, $user);
        if (null === $kadhia) {
            $this->logRejected('KADHIA_NOT_FOUND', $kadhiaId, $slotId, $userId);
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        if (KadhiaStatus::Draft !== $kadhia->getStatus()) {
            // Idempotent: if the Kadhia is already submitted and an order exists, return it.
            $activeOrder = $this->orderRepository->findActiveByKadhia($kadhia);
            if (null !== $activeOrder) {
                return $this->orderOutputFactory->toOutput($activeOrder);
            }
            $this->logRejected('KADHIA_NOT_DRAFT', $kadhiaId, $slotId, $userId);
            throw new UnprocessableEntityHttpException('KADHIA_NOT_DRAFT');
        }

        $shop = $kadhia->getShop();
        $storeId = $shop->getId()->toRfc4122();

        if (!$shop->isActive()) {
            $this->logRejected('STORE_NOT_FOUND', $kadhiaId, $slotId, $userId, $storeId);
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        if (!Uuid::isValid((string) $slotId)) {
            $this->logRejected('PICKUP_SLOT_NOT_FOUND', $kadhiaId, $slotId, $userId, $storeId);
            throw new NotFoundHttpException('PICKUP_SLOT_NOT_FOUND');
        }

        $slot = $this->pickupSlotRepository->find($slotId);
        if (null === $slot || !$slot->isActive() || !$slot->getShop()->getId()->equals($shop->getId())) {
            $this->logRejected('PICKUP_SLOT_NOT_FOUND', $kadhiaId, $slotId, $userId, $storeId);
            throw new NotFoundHttpException('PICKUP_SLOT_NOT_FOUND');
        }

        if ($slot->isFull()) {
            $this->logRejected('PICKUP_SLOT_FULL', $kadhiaId, $slotId, $userId, $storeId);
            throw new UnprocessableEntityHttpException('PICKUP_SLOT_FULL');
        }

        $now = $this->clock->now();
        $slotStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt());
        $slotEndsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt());

        if (!PickupSlotDuration::isExactlyOneHour($slotStartsAt, $slotEndsAt)) {
            $this->logRejected('PICKUP_SLOT_MUST_LAST_ONE_HOUR', $kadhiaId, $slotId, $userId, $storeId);
            throw new UnprocessableEntityHttpException('PICKUP_SLOT_MUST_LAST_ONE_HOUR');
        }

        if ($slotEndsAt <= $now) {
            $this->logRejected('PICKUP_SLOT_EXPIRED', $kadhiaId, $slotId, $userId, $storeId);
            throw new UnprocessableEntityHttpException('PICKUP_SLOT_EXPIRED');
        }

        if ($this->exceptionalClosureRepository->hasActiveOverlapForShop($shop, $slotStartsAt, $slotEndsAt)) {
            $this->logRejected('PICKUP_SLOT_CLOSED', $kadhiaId, $slotId, $userId, $storeId);
            throw new UnprocessableEntityHttpException('PICKUP_SLOT_CLOSED');
        }

        if ($kadhia->getLines()->isEmpty()) {
            $this->logRejected('KADHIA_EMPTY', $kadhiaId, $slotId, $userId, $storeId);
            throw new UnprocessableEntityHttpException('KADHIA_EMPTY');
        }

        foreach ($kadhia->getLines() as $kadhiaLine) {
            $product = $kadhiaLine->getMerchantProduct();
            if (!$product->isAvailable() || !$product->isVisible()) {
                $this->logRejected('PRODUCT_UNAVAILABLE', $kadhiaId, $slotId, $userId, $storeId);
                throw new UnprocessableEntityHttpException('PRODUCT_UNAVAILABLE');
            }
        }

        $existingOrder = $this->orderRepository->findPartiallyAcceptedByKadhia($kadhia);
        if (null !== $existingOrder) {
            try {
                $this->denyLatePartialAcceptanceResubmission($existingOrder->getPickupSlot() ?? $slot);
            } catch (UnprocessableEntityHttpException $e) {
                $this->logRejected('PARTIAL_ACCEPTANCE_EXPIRED', $kadhiaId, $slotId, $userId, $storeId);
                throw $e;
            }
        }

        try {
            /** @var SubmittedOrderResult $result */
            $result = $this->entityManager->wrapInTransaction(
                function () use ($data, $user, $shop, $slot, $kadhia, $existingOrder): SubmittedOrderResult {
                    return null !== $existingOrder
                        ? $this->resubmit($data, $slot, $kadhia, $existingOrder)
                        : $this->firstSubmit($data, $user, $shop, $slot, $kadhia);
                }
            );
        } catch (\RuntimeException $e) {
            if ('PICKUP_SLOT_FULL' === $e->getMessage()) {
                $this->logRejected('PICKUP_SLOT_FULL', $kadhiaId, $slotId, $userId, $storeId);
                throw new UnprocessableEntityHttpException('PICKUP_SLOT_FULL');
            }
            $this->logger->error('order.submit.failed', [
                'kadhia_id' => $kadhiaId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            // Catches DBAL/ORM exceptions that do not extend \RuntimeException in DBAL 4.
            $this->logger->error('order.submit.transaction_failed', [
                'kadhia_id' => $kadhiaId,
                'slot_id' => $slotId,
                'user_id' => $userId,
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $orderId = $result->order->getId()->toRfc4122();

        try {
            $this->merchantResponseTimeoutScheduler->scheduleForSubmittedOrder($result->order);
            $this->logger->info('order.submit.timeout_scheduled', [
                'order_id' => $orderId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('order.submit.timeout_schedule_failed', [
                'order_id' => $orderId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $result->output;
    }

    private function denyLatePartialAcceptanceResubmission(\App\Entity\PickupSlot $slot): void
    {
        $now = $this->clock->now();
        $slotStartsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt());
        if ($now >= $slotStartsAt) {
            throw new UnprocessableEntityHttpException('PARTIAL_ACCEPTANCE_EXPIRED');
        }

        $expiresAt = $slotStartsAt->modify('-'.$this->partialAcceptanceExpirationLeadSeconds.' seconds');
        if ($now >= $expiresAt) {
            throw new UnprocessableEntityHttpException('PARTIAL_ACCEPTANCE_EXPIRED');
        }
    }

    private function firstSubmit(
        SubmitOrderInput $data,
        User $user,
        \App\Entity\Shop $shop,
        \App\Entity\PickupSlot $slot,
        \App\Entity\Kadhia $kadhia,
    ): SubmittedOrderResult {
        $order = (new Order())
            ->setCustomer($user)
            ->setShop($shop)
            ->setKadhia($kadhia)
            ->setPickupSlot($slot)
            ->setNotes($data->notes);

        $this->entityManager->persist($order);

        foreach ($kadhia->getLines() as $kadhiaLine) {
            $unitPriceTnd = $kadhiaLine->getUnitPriceTnd();
            $lineTotalTnd = bcmul($unitPriceTnd, (string) $kadhiaLine->getQuantity(), 3);

            $orderLine = (new OrderLine())
                ->setMerchantProduct($kadhiaLine->getMerchantProduct())
                ->setQuantity($kadhiaLine->getQuantity())
                ->setUnitPriceTnd($unitPriceTnd)
                ->setLineTotalTnd($lineTotalTnd);

            $order->addLine($orderLine);
            $this->entityManager->persist($orderLine);
        }

        $order->recomputeTotal();
        $this->orderNumberGenerator->assignNextIfMissing($order);
        $order->submit();
        $this->orderStatusLogRecorder->record($order, OrderStatus::Submitted);

        // Atomic conditional UPDATE prevents concurrent over-booking.
        $booked = $this->entityManager->getConnection()->executeStatement(
            'UPDATE pickup_slots SET booked_count = booked_count + 1 WHERE id = :id AND booked_count < capacity',
            ['id' => $slot->getId()],
            ['id' => 'uuid'],
        );

        if (0 === $booked) {
            throw new \RuntimeException('PICKUP_SLOT_FULL');
        }

        $kadhia->setStatus(KadhiaStatus::Submitted);
        $this->notificationService->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();
        $this->logger->info('order.submitted', [
            'order_id' => $order->getId()->toRfc4122(),
            'store_id' => $order->getShop()->getId()->toRfc4122(),
            'submission_type' => 'first',
        ]);

        return new SubmittedOrderResult($order, $this->orderOutputFactory->toOutput($order));
    }

    private function resubmit(
        SubmitOrderInput $data,
        \App\Entity\PickupSlot $slot,
        \App\Entity\Kadhia $kadhia,
        Order $order,
    ): SubmittedOrderResult {
        $oldSlot = $order->getPickupSlot();
        $sameSlot = null !== $oldSlot && $oldSlot->getId()->equals($slot->getId());

        if (!$sameSlot) {
            if (null !== $oldSlot) {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE pickup_slots SET booked_count = GREATEST(booked_count - 1, 0) WHERE id = :id',
                    ['id' => $oldSlot->getId()],
                    ['id' => 'uuid'],
                );
            }

            $booked = $this->entityManager->getConnection()->executeStatement(
                'UPDATE pickup_slots SET booked_count = booked_count + 1 WHERE id = :id AND booked_count < capacity',
                ['id' => $slot->getId()],
                ['id' => 'uuid'],
            );

            if (0 === $booked) {
                throw new \RuntimeException('PICKUP_SLOT_FULL');
            }
        }

        $order->setPickupSlot($slot);
        $order->setNotes($data->notes);

        // Index existing lines by product UUID for in-place reuse.
        $existingLines = [];
        foreach ($order->getLines() as $line) {
            $existingLines[$line->getMerchantProduct()->getId()->toRfc4122()] = $line;
        }

        $kadhiaProductIds = [];
        foreach ($kadhia->getLines() as $kadhiaLine) {
            $productId = $kadhiaLine->getMerchantProduct()->getId()->toRfc4122();
            $kadhiaProductIds[$productId] = true;
            $unitPriceTnd = $kadhiaLine->getUnitPriceTnd();
            $lineTotalTnd = bcmul($unitPriceTnd, (string) $kadhiaLine->getQuantity(), 3);

            if (isset($existingLines[$productId])) {
                // Reuse existing row — avoids delete+insert on same (order_id, merchant_product_id).
                $existingLines[$productId]
                    ->setQuantity($kadhiaLine->getQuantity())
                    ->setUnitPriceTnd($unitPriceTnd)
                    ->setLineTotalTnd($lineTotalTnd);
            } else {
                $orderLine = (new OrderLine())
                    ->setMerchantProduct($kadhiaLine->getMerchantProduct())
                    ->setQuantity($kadhiaLine->getQuantity())
                    ->setUnitPriceTnd($unitPriceTnd)
                    ->setLineTotalTnd($lineTotalTnd);

                $order->addLine($orderLine);
                $this->entityManager->persist($orderLine);
            }
        }

        // Remove lines whose products are no longer in the Kadhia.
        foreach ($existingLines as $productId => $line) {
            if (!isset($kadhiaProductIds[$productId])) {
                $order->removeLine($line);
            }
        }

        $order->recomputeTotal();
        $this->orderNumberGenerator->assignNextIfMissing($order);
        $order->resubmit();
        $this->orderStatusLogRecorder->record($order, OrderStatus::Submitted);

        $kadhia->setStatus(KadhiaStatus::Submitted);
        $this->notificationService->notifyMerchantOrderSubmitted($order);
        $this->entityManager->flush();
        $this->logger->info('order.submitted', [
            'order_id' => $order->getId()->toRfc4122(),
            'store_id' => $order->getShop()->getId()->toRfc4122(),
            'submission_type' => 'resubmission',
        ]);

        return new SubmittedOrderResult($order, $this->orderOutputFactory->toOutput($order));
    }

    private function logRejected(string $reason, string $kadhiaId, ?string $slotId = null, ?string $userId = null, ?string $storeId = null): void
    {
        $this->logger->warning('order.submit.rejected', [
            'reason' => $reason,
            'kadhia_id' => $kadhiaId,
            'slot_id' => $slotId,
            'user_id' => $userId,
            'store_id' => $storeId,
        ]);
    }
}
