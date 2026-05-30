<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PickupSlotRuleGenerationOutput;
use App\Dto\GenerateSlotsInput;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotRuleGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PickupSlotRuleGenerationOutput
    {
        if (!$data instanceof GenerateSlotsInput) {
            throw new \InvalidArgumentException('GenerateSlotsInput expected.');
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

        $result = $this->pickupSlotRuleGenerator->generateForShop($shop, horizonMonths: $data->horizonMonths);

        $this->logger->info('merchant.pickup_rules.generated', [
            'store_id' => $storeId,
            'generated_count' => $result->generatedCount,
            'skipped_existing' => $result->skippedExistingCount,
            'skipped_closure' => $result->skippedClosureCount,
        ]);

        return new PickupSlotRuleGenerationOutput(
            storeId: $shop->getId()->toRfc4122(),
            generatedCount: $result->generatedCount,
            skippedExistingCount: $result->skippedExistingCount,
            skippedClosureCount: $result->skippedClosureCount,
            horizonStart: $result->horizonStart->format(\DateTimeInterface::ATOM),
            horizonEnd: $result->horizonEnd->format(\DateTimeInterface::ATOM),
        );
    }
}
