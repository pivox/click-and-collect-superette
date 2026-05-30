<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantOrderHistoryCustomerOutput;
use App\ApiResource\MerchantOrderHistoryItemOutput;
use App\ApiResource\MerchantOrderHistoryOutput;
use App\ApiResource\MerchantOrderHistoryPickupSlotOutput;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantOrderHistoryOutput>
 */
final readonly class MerchantOrderHistoryProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantOrderHistoryOutput
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

        $request = $this->requestStack->getCurrentRequest();
        $statuses = $this->parseStatus($request?->query->get('status'));
        $dateFrom = $this->parseDate($request?->query->get('date_from'), false, 'ORDER_HISTORY_INVALID_DATE_FROM');
        $dateTo = $this->parseDate($request?->query->get('date_to'), true, 'ORDER_HISTORY_INVALID_DATE_TO');
        if (null !== $dateFrom && null !== $dateTo && $dateFrom > $dateTo) {
            throw new UnprocessableEntityHttpException('ORDER_HISTORY_INVALID_DATE_RANGE');
        }

        $query = trim((string) ($request?->query->get('query') ?? ''));
        $query = '' === $query ? null : $query;
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ORDER_HISTORY_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ORDER_HISTORY_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepository->findHistoryForShop($shop, $statuses, $dateFrom, $dateTo, $query, $limit, $offset);
        $total = $this->orderRepository->countHistoryForShop($shop, $statuses, $dateFrom, $dateTo, $query);

        return new MerchantOrderHistoryOutput(
            id: $storeId,
            items: array_map(self::toItemOutput(...), $orders),
            page: $page,
            limit: $limit,
            total: $total,
        );
    }

    /**
     * @return list<OrderStatus>|null
     */
    private function parseStatus(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return null;
        }

        $statuses = [];
        foreach (explode(',', $raw) as $rawStatus) {
            $status = OrderStatus::tryFrom(trim($rawStatus));
            if (null === $status || OrderStatus::Draft === $status) {
                throw new UnprocessableEntityHttpException('ORDER_HISTORY_INVALID_STATUS');
            }

            if (!\in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    private function parseDate(?string $raw, bool $endOfDay, string $errorCode): ?\DateTimeImmutable
    {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return null;
        }

        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new \DateTimeZone('Africa/Tunis'));
        if (false === $date || $date->format('Y-m-d') !== $raw) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        return $endOfDay ? $date->setTime(23, 59, 59, 999999) : $date->setTime(0, 0);
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        return $value;
    }

    private static function toItemOutput(Order $order): MerchantOrderHistoryItemOutput
    {
        $status = $order->getStatus();
        $customer = $order->getCustomer();
        $slot = $order->getPickupSlot();
        $canExposeCustomerContact = !\in_array(
            $status,
            [OrderStatus::Rejected, OrderStatus::Completed, OrderStatus::Cancelled],
            true,
        );

        return new MerchantOrderHistoryItemOutput(
            id: $order->getId()->toRfc4122(),
            status: $status->value,
            statusLabelFr: $status->labelFr(),
            statusLabelAr: $status->labelAr(),
            customer: new MerchantOrderHistoryCustomerOutput(
                firstName: $canExposeCustomerContact ? $customer->getFirstName() : null,
                lastName: $canExposeCustomerContact ? $customer->getLastName() : null,
                phone: $canExposeCustomerContact ? $customer->getPhone() : null,
            ),
            total: $order->getTotalTnd(),
            pickupSlot: null === $slot ? null : new MerchantOrderHistoryPickupSlotOutput(
                startsAt: PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()),
                endsAt: PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()),
            ),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
