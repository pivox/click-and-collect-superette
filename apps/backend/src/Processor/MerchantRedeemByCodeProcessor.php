<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantRedeemByCodeOutput;
use App\Dto\MerchantRedeemByCodeInput;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantRedeemByCodeInput, MerchantRedeemByCodeOutput>
 */
final readonly class MerchantRedeemByCodeProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private OrderTransitionService $orderTransitionService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantRedeemByCodeOutput
    {
        if (!$data instanceof MerchantRedeemByCodeInput) {
            throw new \InvalidArgumentException('MerchantRedeemByCodeInput expected.');
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

        $order = $this->orderRepository->findReadyByPickupCodeAndShop($data->pickupCode, $shop);
        if (null === $order) {
            throw new NotFoundHttpException('PICKUP_CODE_NOT_FOUND');
        }

        try {
            $this->orderTransitionService->completeByCode($order, $data->pickupCode);
        } catch (\LogicException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        $this->entityManager->flush();

        return new MerchantRedeemByCodeOutput(
            orderId: $order->getId()->toRfc4122(),
            status: $order->getStatus()->value,
        );
    }
}
