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
use App\Factory\OrderOutputFactory;
use App\Repository\KadhiaRepository;
use App\Repository\OrderRepository;
use App\Repository\PickupSlotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
        private KadhiaRepository $kadhiaRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
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
        if (!$data instanceof SubmitOrderInput) {
            throw new \InvalidArgumentException('SubmitOrderInput expected.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $kadhiaId = (string) ($uriVariables['kadhiaId'] ?? '');
        if (!Uuid::isValid($kadhiaId)) {
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        $kadhia = $this->kadhiaRepository->findByIdAndCustomer($kadhiaId, $user);
        if (null === $kadhia) {
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        if (KadhiaStatus::Draft !== $kadhia->getStatus()) {
            throw new UnprocessableEntityHttpException('KADHIA_NOT_DRAFT');
        }

        $shop = $kadhia->getShop();
        if (!$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        if (!Uuid::isValid($data->pickupSlotId)) {
            throw new NotFoundHttpException('PICKUP_SLOT_NOT_FOUND');
        }

        $slot = $this->pickupSlotRepository->find($data->pickupSlotId);
        if (null === $slot || !$slot->isActive() || !$slot->getShop()->getId()->equals($shop->getId())) {
            throw new NotFoundHttpException('PICKUP_SLOT_NOT_FOUND');
        }

        if ($slot->isFull()) {
            throw new UnprocessableEntityHttpException('PICKUP_SLOT_FULL');
        }

        if ($slot->getEndsAt() <= new \DateTimeImmutable()) {
            throw new UnprocessableEntityHttpException('PICKUP_SLOT_EXPIRED');
        }

        if ($kadhia->getLines()->isEmpty()) {
            throw new UnprocessableEntityHttpException('KADHIA_EMPTY');
        }

        foreach ($kadhia->getLines() as $kadhiaLine) {
            $product = $kadhiaLine->getMerchantProduct();
            if (!$product->isAvailable() || !$product->isVisible()) {
                throw new UnprocessableEntityHttpException('PRODUCT_UNAVAILABLE');
            }
        }

        $existingOrder = $this->orderRepository->findPartiallyAcceptedByKadhia($kadhia);

        try {
            /** @var OrderOutput $result */
            $result = $this->entityManager->wrapInTransaction(
                function () use ($data, $user, $shop, $slot, $kadhia, $existingOrder): OrderOutput {
                    if (null !== $existingOrder) {
                        return $this->resubmit($data, $slot, $kadhia, $existingOrder);
                    }

                    return $this->firstSubmit($data, $user, $shop, $slot, $kadhia);
                }
            );
        } catch (\RuntimeException $e) {
            if ('PICKUP_SLOT_FULL' === $e->getMessage()) {
                throw new UnprocessableEntityHttpException('PICKUP_SLOT_FULL');
            }
            throw $e;
        }

        return $result;
    }

    private function firstSubmit(
        SubmitOrderInput $data,
        User $user,
        \App\Entity\Shop $shop,
        \App\Entity\PickupSlot $slot,
        \App\Entity\Kadhia $kadhia,
    ): OrderOutput {
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
        $order->submit();

        // Atomic conditional UPDATE prevents concurrent over-booking.
        $booked = $this->entityManager->getConnection()->executeStatement(
            'UPDATE pickup_slots SET booked_count = booked_count + 1 WHERE id = :id AND booked_count < capacity',
            ['id' => $slot->getId()->toBinary()],
        );

        if (0 === $booked) {
            throw new \RuntimeException('PICKUP_SLOT_FULL');
        }

        $kadhia->setStatus(KadhiaStatus::Submitted);
        $this->entityManager->flush();

        return $this->orderOutputFactory->toOutput($order);
    }

    private function resubmit(
        SubmitOrderInput $data,
        \App\Entity\PickupSlot $slot,
        \App\Entity\Kadhia $kadhia,
        Order $order,
    ): OrderOutput {
        $oldSlot = $order->getPickupSlot();
        $sameSlot = null !== $oldSlot && $oldSlot->getId()->equals($slot->getId());

        if (!$sameSlot) {
            if (null !== $oldSlot) {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE pickup_slots SET booked_count = GREATEST(booked_count - 1, 0) WHERE id = :id',
                    ['id' => $oldSlot->getId()->toBinary()],
                );
            }

            $booked = $this->entityManager->getConnection()->executeStatement(
                'UPDATE pickup_slots SET booked_count = booked_count + 1 WHERE id = :id AND booked_count < capacity',
                ['id' => $slot->getId()->toBinary()],
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
        $order->resubmit();

        $kadhia->setStatus(KadhiaStatus::Submitted);
        $this->entityManager->flush();

        return $this->orderOutputFactory->toOutput($order);
    }
}
