<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\PickupSlotRuleRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<object, void>
 */
final readonly class DeleteMerchantPickupSlotRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
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
        $ruleId = (string) ($uriVariables['ruleId'] ?? '');
        if (!Uuid::isValid($storeId) || !Uuid::isValid($ruleId)) {
            throw new NotFoundHttpException('PICKUP_SLOT_RULE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $rule = $this->pickupSlotRuleRepository->findActiveOneForShop($shop, $ruleId);
        if (null === $rule) {
            throw new NotFoundHttpException('PICKUP_SLOT_RULE_NOT_FOUND');
        }

        $rule->setActive(false);
        $this->entityManager->flush();
    }
}
