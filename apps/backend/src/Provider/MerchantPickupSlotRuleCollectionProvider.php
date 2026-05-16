<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantPickupSlotRuleCollectionOutput;
use App\ApiResource\MerchantPickupSlotRuleOutput;
use App\Entity\PickupSlotRule;
use App\Repository\PickupSlotRuleRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantPickupSlotRuleCollectionOutput>
 */
final readonly class MerchantPickupSlotRuleCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantPickupSlotRuleCollectionOutput
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

        $items = array_map(
            $this->toOutput(...),
            $this->pickupSlotRuleRepository->findActiveForShop($shop),
        );

        return new MerchantPickupSlotRuleCollectionOutput(
            total: \count($items),
            items: $items,
        );
    }

    private function toOutput(PickupSlotRule $rule): MerchantPickupSlotRuleOutput
    {
        return new MerchantPickupSlotRuleOutput(
            id: $rule->getId()->toRfc4122(),
            weekday: $rule->getWeekday(),
            startTime: $rule->getStartTime()->format('H:i'),
            endTime: $rule->getEndTime()->format('H:i'),
            capacity: $rule->getCapacity(),
            isActive: $rule->isActive(),
        );
    }
}
