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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
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

        $this->logger->debug('merchant.order_validate_manual.start', [
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        if (OrderStatus::Ready !== $order->getStatus()) {
            $this->logger->warning('merchant.order_validate_manual.rejected', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'reason' => 'ORDER_NOT_READY',
            ]);
            throw new ConflictHttpException('ORDER_NOT_READY');
        }

        try {
            $this->orderTransitionService->completeManually($order, $data->note);
            $this->entityManager->flush();
            $this->logger->info('merchant.order_validated_manually', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'has_note' => '' !== trim($data->note ?? ''),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('merchant.order_validate_manual.failed', [
                'order_id' => $orderId,
                'store_id' => $storeId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return new MerchantValidateManuallyOutput(
            id: $order->getId()->toRfc4122(),
            orderId: $order->getId()->toRfc4122(),
            status: $order->getStatus()->value,
        );
    }
}
