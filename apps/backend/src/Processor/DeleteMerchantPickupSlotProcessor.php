<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<object, void>
 */
final readonly class DeleteMerchantPickupSlotProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRepository $pickupSlotRepository,
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

        $slot->setActive(false);
        $this->entityManager->flush();
    }
}
