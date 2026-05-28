<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantValidateManuallyOutput;
use App\Dto\MerchantValidateManuallyInput;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantValidateManuallyInput, MerchantValidateManuallyOutput>
 */
final readonly class MerchantValidateManuallyProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantValidateManuallyOutput
    {
        if (!$data instanceof MerchantValidateManuallyInput) {
            throw new \InvalidArgumentException('MerchantValidateManuallyInput expected.');
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

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneBy(['id' => $orderId, 'shop' => $shop]);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        if (OrderStatus::Ready !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_READY');
        }

        $this->orderTransitionService->completeManually($order, $data->note);
        $this->entityManager->flush();

        return new MerchantValidateManuallyOutput(
            id: $order->getId()->toRfc4122(),
            orderId: $order->getId()->toRfc4122(),
            status: $order->getStatus()->value,
        );
    }
}
