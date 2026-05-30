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
use App\Service\NotificationService;
use App\Service\OrderStatusLogRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, MerchantOrderOutput>
 */
final readonly class MerchantStartPreparationProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
        private NotificationService $notificationService,
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
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

        $this->logger->debug('merchant.order_preparation.start', [
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        try {
            $order->startPreparing();
            $this->orderStatusLogRecorder->record($order, OrderStatus::Preparing);
            $this->notificationService->notifyCustomerOrderPreparing($order);
            $this->entityManager->flush();
            $this->logger->info('merchant.order_preparation_started', [
                'order_id' => $orderId,
                'store_id' => $storeId,
            ]);
        } catch (\LogicException $e) {
            $this->logger->warning('merchant.order_preparation.rejected', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'reason' => $e->getMessage(),
            ]);
            throw new ConflictHttpException($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('merchant.order_preparation.failed', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return MerchantOrderCollectionProvider::toOutput($order);
    }
}
