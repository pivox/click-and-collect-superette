<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantOrderOutput;
use App\Provider\MerchantOrderCollectionProvider;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\NotificationService;
use App\Service\OrderTransitionService;
use App\Service\PickupReminderScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, MerchantOrderOutput>
 */
final readonly class MerchantMarkReadyProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
        private OrderTransitionService $orderTransitionService,
        private NotificationService $notificationService,
        private PickupReminderScheduler $pickupReminderScheduler,
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

        $this->logger->debug('merchant.order_ready.start', [
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        // State machine: markReady() throws \LogicException if the order is not in the right status.
        // flush() is outside this block so the state change always reaches the DB on success.
        try {
            $this->orderTransitionService->markReady($order);
        } catch (\LogicException $e) {
            $this->logger->warning('merchant.order_ready.rejected', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'reason' => $e->getMessage(),
            ]);
            throw new ConflictHttpException($e->getMessage());
        }

        $this->entityManager->flush();

        $this->logger->info('merchant.order_ready', [
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        // Notification is best-effort: failure must not roll back the ready state.
        try {
            $this->notificationService->notifyCustomerOrderReady($order);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('merchant.order_ready.notification_failed', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
        }

        try {
            $this->pickupReminderScheduler->scheduleForReadyOrder($order);
            $this->logger->info('merchant.order_ready.pickup_reminder_scheduled', [
                'order_id' => $orderId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('merchant.order_ready.pickup_reminder_schedule_failed', [
                'order_id' => $orderId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return MerchantOrderCollectionProvider::toOutput($order);
    }
}
