<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantPickupSlotPatchInput;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use App\Service\PickupSlotDuration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantPickupSlotPatchInput, void>
 */
final readonly class UpdateMerchantPickupSlotProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof MerchantPickupSlotPatchInput) {
            throw new \InvalidArgumentException('MerchantPickupSlotPatchInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $slotId = (string) ($uriVariables['slotId'] ?? '');
        if (!Uuid::isValid($storeId) || !Uuid::isValid($slotId)) {
            throw new NotFoundHttpException('PICKUP_SLOT_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $slot = $this->pickupSlotRepository->findOneForShop($shop, $slotId);
        if (null === $slot) {
            throw new NotFoundHttpException('PICKUP_SLOT_NOT_FOUND');
        }

        $startsAt = null !== $data->startsAt
            ? PickupSlotDisplayTime::fromPayloadInstant($data->startsAt)
            : PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt());
        $endsAt = null !== $data->endsAt
            ? PickupSlotDisplayTime::fromPayloadInstant($data->endsAt)
            : PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt());
        $isActive = $data->isActive ?? $slot->isActive();
        if ($startsAt >= $endsAt) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_STARTS_AT_MUST_BE_BEFORE_ENDS_AT');
        }

        if (!PickupSlotDuration::isExactlyOneHour($startsAt, $endsAt)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_MUST_LAST_ONE_HOUR');
        }

        if (null !== $data->capacity && $data->capacity < $slot->getBookedCount()) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_CAPACITY_BELOW_BOOKED_COUNT');
        }

        if ($isActive && $this->pickupSlotRepository->hasActiveOverlapForShop($shop, $startsAt, $endsAt, $slot)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_OVERLAPS_EXISTING_SLOT');
        }

        if ($isActive && $this->exceptionalClosureRepository->hasActiveOverlapForShop($shop, $startsAt, $endsAt)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_OVERLAPS_EXCEPTIONAL_CLOSURE');
        }

        if (null !== $data->startsAt) {
            $slot->setStartsAt($startsAt);
        }
        if (null !== $data->endsAt) {
            $slot->setEndsAt($endsAt);
        }
        if (null !== $data->capacity) {
            $slot->setCapacity($data->capacity);
        }
        if (null !== $data->isActive) {
            $slot->setActive($data->isActive);
        }

        $this->entityManager->flush();
    }
}
