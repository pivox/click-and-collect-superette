<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantPickupSlotOutput;
use App\Entity\PickupSlot;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantPickupSlotOutput>
 */
final readonly class MerchantPickupSlotCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<MerchantPickupSlotOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        return array_map(
            $this->toOutput(...),
            $this->pickupSlotRepository->findForShop($shop),
        );
    }

    private function toOutput(PickupSlot $slot): MerchantPickupSlotOutput
    {
        return new MerchantPickupSlotOutput(
            id: $slot->getId()->toRfc4122(),
            startsAt: PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()),
            endsAt: PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()),
            capacity: $slot->getCapacity(),
            bookedCount: $slot->getBookedCount(),
            isActive: $slot->isActive(),
        );
    }
}
