<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantOrderDetailOutput;
use App\Dto\PrepareOrderLineInput;
use App\Enum\OrderStatus;
use App\Provider\MerchantOrderItemProvider;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<PrepareOrderLineInput, MerchantOrderDetailOutput>
 */
final readonly class MerchantPrepareOrderLineProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantOrderDetailOutput
    {
        if (!$data instanceof PrepareOrderLineInput || null === $data->prepared) {
            throw new BadRequestHttpException('PREPARED_REQUIRED');
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

        $order = $this->orderRepository->findOneByShopAndIdWithDetail($shop, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        if (OrderStatus::Preparing !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_PREPARING');
        }

        $merchantProductId = (string) ($uriVariables['merchantProductId'] ?? '');
        if (!Uuid::isValid($merchantProductId)) {
            throw new NotFoundHttpException('ORDER_LINE_NOT_FOUND');
        }

        $merchantProductUuid = Uuid::fromString($merchantProductId);
        $line = null;
        foreach ($order->getLines() as $orderLine) {
            if ($orderLine->getMerchantProduct()->getId()->equals($merchantProductUuid)) {
                $line = $orderLine;
                break;
            }
        }

        if (null === $line) {
            throw new NotFoundHttpException('ORDER_LINE_NOT_FOUND');
        }

        $line->markPrepared($data->prepared);
        $this->entityManager->flush();

        return MerchantOrderItemProvider::toOutput($order);
    }
}
