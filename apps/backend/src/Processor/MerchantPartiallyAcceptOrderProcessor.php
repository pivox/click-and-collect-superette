<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantOrderOutput;
use App\Dto\PartiallyAcceptOrderInput;
use App\Entity\KadhiaLine;
use App\Enum\KadhiaStatus;
use App\Enum\OrderStatus;
use App\Provider\MerchantOrderCollectionProvider;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\NotificationService;
use App\Service\OrderStatusLogRecorder;
use App\Service\PartialAcceptanceExpirationScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<PartiallyAcceptOrderInput, MerchantOrderOutput>
 */
final readonly class MerchantPartiallyAcceptOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
        private OrderStatusLogRecorder $orderStatusLogRecorder,
        private NotificationService $notificationService,
        private PartialAcceptanceExpirationScheduler $partialAcceptanceExpirationScheduler,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantOrderOutput
    {
        if (!$data instanceof PartiallyAcceptOrderInput) {
            throw new \InvalidArgumentException('PartiallyAcceptOrderInput expected.');
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

        $rejectedProductIds = array_values(array_unique($data->rejectedMerchantProductIds));
        if ([] === $rejectedProductIds) {
            throw new UnprocessableEntityHttpException('NO_LINES_REJECTED');
        }

        $orderLineProductIds = [];
        foreach ($order->getLines() as $line) {
            $orderLineProductIds[$line->getMerchantProduct()->getId()->toRfc4122()] = true;
        }

        foreach ($rejectedProductIds as $productId) {
            if (!isset($orderLineProductIds[$productId])) {
                throw new UnprocessableEntityHttpException('ORDER_LINE_NOT_FOUND');
            }
        }

        if (\count($rejectedProductIds) === \count($orderLineProductIds)) {
            throw new UnprocessableEntityHttpException('USE_REJECT_ENDPOINT');
        }

        $kadhia = $order->getKadhia();
        if (null === $kadhia) {
            throw new ConflictHttpException('ORDER_KADHIA_REQUIRED');
        }

        $kadhiaLineProductIds = [];
        foreach ($kadhia->getLines() as $line) {
            $kadhiaLineProductIds[$line->getMerchantProduct()->getId()->toRfc4122()] = true;
        }

        foreach ($rejectedProductIds as $productId) {
            if (!isset($kadhiaLineProductIds[$productId])) {
                throw new ConflictHttpException('KADHIA_LINE_NOT_FOUND');
            }
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($order, $kadhia, $rejectedProductIds, $data): void {
                $order->partiallyAccept($data->notes);

                foreach ($kadhia->getLines()->toArray() as $line) {
                    if (!$line instanceof KadhiaLine) {
                        continue;
                    }

                    $merchantProductId = $line->getMerchantProduct()->getId()->toRfc4122();
                    if (\in_array($merchantProductId, $rejectedProductIds, true)) {
                        $kadhia->removeLine($line);
                    }
                }

                $kadhia->setStatus(KadhiaStatus::Draft);
                $this->orderStatusLogRecorder->record($order, OrderStatus::PartiallyAccepted, $data->notes);
                $this->notificationService->notifyCustomerOrderPartiallyAccepted($order);
                $this->entityManager->flush();
            });
        } catch (\LogicException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

        $this->partialAcceptanceExpirationScheduler->scheduleForPartiallyAcceptedOrder($order);

        return MerchantOrderCollectionProvider::toOutput($order);
    }
}
