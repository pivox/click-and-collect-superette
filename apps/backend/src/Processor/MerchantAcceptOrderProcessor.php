<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantOrderOutput;
use App\Enum\OrderStatus;
use App\Provider\MerchantOrderCollectionProvider;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderStatusLogRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, MerchantOrderOutput>
 */
final readonly class MerchantAcceptOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantOrderOutput
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

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneByShopAndId($shop, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        try {
            $order->accept();
            $this->orderStatusLogRecorder->record($order, OrderStatus::Accepted);
        } catch (\LogicException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

        $this->entityManager->flush();

        return MerchantOrderCollectionProvider::toOutput($order);
    }
}
