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
use App\Service\RateLimit\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
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
        private RateLimiterInterface $rateLimiter,
        private Security $security,
        private LoggerInterface $logger,
        private bool $rateLimitEnabled,
        private int $pickupCodeLimit,
        private int $pickupCodeWindowSeconds,
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

        $auditContext = [
            'store_id' => $storeId,
            'merchant_identifier_hash' => hash('sha256', $this->security->getUser()?->getUserIdentifier() ?? ''),
        ];
        if ($this->rateLimitEnabled) {
            $decision = $this->rateLimiter->consume(
                'pickup_code_merchant_shop',
                $auditContext['merchant_identifier_hash'].':'.$storeId,
                $this->pickupCodeLimit,
                $this->pickupCodeWindowSeconds,
            );
            if (!$decision->accepted) {
                $this->logger->warning('pickup_code_rate_limited', $auditContext);
                throw new TooManyRequestsHttpException($decision->retryAfterSeconds, 'RATE_LIMITED');
            }
        }

        return $this->entityManager->getConnection()->transactional(function () use ($data, $shop, $auditContext): MerchantRedeemByCodeOutput {
            $order = $this->orderRepository->findReadyByPickupCodeAndShop($data->pickupCode, $shop);
            if (null === $order) {
                $this->logger->warning('pickup_code_rejected', $auditContext);
                throw new NotFoundHttpException('PICKUP_CODE_NOT_FOUND');
            }

            try {
                $this->orderTransitionService->completeByCode($order, $data->pickupCode);
            } catch (\LogicException $e) {
                $this->logger->warning('pickup_code_rejected', $auditContext);
                throw new NotFoundHttpException('PICKUP_CODE_NOT_FOUND', $e);
            }

            $this->entityManager->flush();

            return new MerchantRedeemByCodeOutput(
                orderId: $order->getId()->toRfc4122(),
                status: $order->getStatus()->value,
            );
        });
    }
}
