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
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
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
        private ShopRepository $shopRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private KadhiaRepository $kadhiaRepository,
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

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
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

        $kadhia = $this->kadhiaRepository->findDraftByCustomerAndShop($user, $shop);
        if (null === $kadhia) {
            throw new UnprocessableEntityHttpException('KADHIA_NOT_FOUND');
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

        try {
            /** @var OrderOutput $result */
            $result = $this->entityManager->wrapInTransaction(function () use ($data, $user, $shop, $slot, $kadhia): OrderOutput {
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

                $slot->book();

                $kadhia->setStatus(KadhiaStatus::Submitted);

                $this->entityManager->flush();

                return $this->orderOutputFactory->toOutput($order);
            });
        } catch (\RuntimeException $e) {
            if ('PICKUP_SLOT_FULL' === $e->getMessage()) {
                throw new UnprocessableEntityHttpException('PICKUP_SLOT_FULL');
            }
            throw $e;
        }

        return $result;
    }
}
