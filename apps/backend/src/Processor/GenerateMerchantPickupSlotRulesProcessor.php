<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PickupSlotRuleGenerationOutput;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotRuleGenerator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<object, PickupSlotRuleGenerationOutput>
 */
final readonly class GenerateMerchantPickupSlotRulesProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private PickupSlotRuleGenerator $pickupSlotRuleGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PickupSlotRuleGenerationOutput
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

        $result = $this->pickupSlotRuleGenerator->generateForShop($shop);

        return new PickupSlotRuleGenerationOutput(
            generatedCount: $result->generatedCount,
            skippedExistingCount: $result->skippedExistingCount,
            horizonStart: $result->horizonStart->format(\DateTimeInterface::ATOM),
            horizonEnd: $result->horizonEnd->format(\DateTimeInterface::ATOM),
        );
    }
}
