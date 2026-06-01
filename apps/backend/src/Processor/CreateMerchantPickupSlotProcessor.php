<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantPickupSlotCreateInput;
use App\Entity\PickupSlot;
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
 * @implements ProcessorInterface<MerchantPickupSlotCreateInput, void>
 */
final readonly class CreateMerchantPickupSlotProcessor implements ProcessorInterface
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
        if (!$data instanceof MerchantPickupSlotCreateInput) {
            throw new \InvalidArgumentException('MerchantPickupSlotCreateInput expected.');
        }
        if (null === $data->startsAt || null === $data->endsAt || null === $data->capacity) {
            throw new \InvalidArgumentException('Validated merchant pickup slot payload expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $startsAt = PickupSlotDisplayTime::fromPayloadInstant($data->startsAt);
        $endsAt = PickupSlotDisplayTime::fromPayloadInstant($data->endsAt);

        if (!PickupSlotDuration::isExactlyOneHour($startsAt, $endsAt)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_MUST_LAST_ONE_HOUR');
        }

        if ($this->pickupSlotRepository->hasActiveOverlapForShop($shop, $startsAt, $endsAt)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_OVERLAPS_EXISTING_SLOT');
        }

        if ($this->exceptionalClosureRepository->hasActiveOverlapForShop($shop, $startsAt, $endsAt)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_OVERLAPS_EXCEPTIONAL_CLOSURE');
        }

        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setCapacity($data->capacity)
            ->setActive(true);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();
    }
}
