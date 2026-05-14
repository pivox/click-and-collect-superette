<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantDashboardOutput;
use App\ApiResource\MerchantDashboardPickupSlotOutput;
use App\Entity\PickupSlot;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantDashboardOutput>
 */
final readonly class MerchantDashboardProvider implements ProviderInterface
{
    private const DASHBOARD_TIMEZONE = 'Africa/Tunis';

    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private PickupSlotRepository $pickupSlotRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantDashboardOutput
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

        $timezone = new \DateTimeZone(self::DASHBOARD_TIMEZONE);
        $now = new \DateTimeImmutable('now', $timezone);
        $dayStart = $now->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $ordersByStatus = $this->orderRepository->countByStatusForShopBetweenPickupSlotStarts($shop, $dayStart, $dayEnd);
        foreach (OrderStatus::cases() as $status) {
            $ordersByStatus[$status->value] ??= 0;
        }
        ksort($ordersByStatus);

        $urgentSubmittedCount = $this->orderRepository->countUrgentSubmittedForShopBetweenPickupSlotStarts(
            $shop,
            $now,
            $now->modify('+3 hours'),
        );

        $slots = array_map(
            $this->toPickupSlotOutput(...),
            $this->pickupSlotRepository->findForShopBetweenStartsAt($shop, $dayStart, $dayEnd),
        );

        return new MerchantDashboardOutput(
            storeId: $shop->getId()->toRfc4122(),
            date: $dayStart->format('Y-m-d'),
            totalOrdersToday: array_sum($ordersByStatus),
            ordersByStatus: $ordersByStatus,
            submittedCount: $ordersByStatus[OrderStatus::Submitted->value],
            acceptedCount: $ordersByStatus[OrderStatus::Accepted->value],
            partiallyAcceptedCount: $ordersByStatus[OrderStatus::PartiallyAccepted->value],
            preparingCount: $ordersByStatus[OrderStatus::Preparing->value],
            readyCount: $ordersByStatus[OrderStatus::Ready->value],
            cancelledCount: $ordersByStatus[OrderStatus::Cancelled->value],
            rejectedCount: $ordersByStatus[OrderStatus::Rejected->value],
            completedCount: $ordersByStatus[OrderStatus::Completed->value],
            urgentSubmittedCount: $urgentSubmittedCount,
            pickupSlotsToday: $slots,
        );
    }

    private function toPickupSlotOutput(PickupSlot $slot): MerchantDashboardPickupSlotOutput
    {
        return new MerchantDashboardPickupSlotOutput(
            pickupSlotId: $slot->getId()->toRfc4122(),
            startsAt: $slot->getStartsAt()->format(\DateTimeInterface::ATOM),
            endsAt: $slot->getEndsAt()->format(\DateTimeInterface::ATOM),
            capacity: $slot->getCapacity(),
            bookedCount: $slot->getBookedCount(),
            remainingCapacity: $slot->getAvailableCount(),
        );
    }
}
