<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SubscriptionPaymentListOutput;
use App\Entity\User;
use App\Repository\SubscriptionPaymentRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<SubscriptionPaymentListOutput>
 */
final readonly class MerchantSubscriptionPaymentCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private Security $security,
        private SubscriptionPaymentRepository $subscriptionPaymentRepository,
        private SubscriptionPaymentOutputFactory $outputFactory,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SubscriptionPaymentListOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'MERCHANT_SUBSCRIPTION_PAYMENT_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'MERCHANT_SUBSCRIPTION_PAYMENT_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $items = array_map(
            fn ($payment) => $this->outputFactory->fromSubscriptionPayment($payment),
            $this->subscriptionPaymentRepository->findPaginatedForMerchant($merchant, $limit, $offset),
        );

        return new SubscriptionPaymentListOutput(
            id: 'merchant-subscription-payments',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->subscriptionPaymentRepository->countForMerchant($merchant),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }
        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }
        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }
}
