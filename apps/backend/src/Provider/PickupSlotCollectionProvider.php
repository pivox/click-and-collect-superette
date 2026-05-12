<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PickupSlotCollectionOutput;
use App\ApiResource\PickupSlotOutput;
use App\Entity\PickupSlot;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<PickupSlotCollectionOutput>
 */
final readonly class PickupSlotCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRepository $pickupSlotRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PickupSlotCollectionOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $items = array_map(
            static fn (PickupSlot $slot): PickupSlotOutput => new PickupSlotOutput(
                id: $slot->getId()->toRfc4122(),
                startsAt: $slot->getStartsAt()->format(\DateTimeInterface::ATOM),
                endsAt: $slot->getEndsAt()->format(\DateTimeInterface::ATOM),
                capacity: $slot->getCapacity(),
                availableCount: $slot->getAvailableCount(),
            ),
            $this->pickupSlotRepository->findAvailableForShop($shop),
        );

        return new PickupSlotCollectionOutput($storeId, $items);
    }
}
