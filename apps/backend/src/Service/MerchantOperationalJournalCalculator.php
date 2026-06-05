<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\AdminMerchantOpsJournalOutput;
use App\Entity\AdminAuditLog;
use App\Entity\Incident;
use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\SubscriptionPaymentReminder;
use App\Entity\User;
use App\Enum\IncidentStatus;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MerchantOperationalJournalCalculator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function calculate(User $merchant, ?\DateTimeImmutable $now = null): AdminMerchantOpsJournalOutput
    {
        $overdueCutoff = PickupSlotDisplayTime::fromPayloadInstant($now ?? new \DateTimeImmutable());
        $lastActivity = $this->findLastActivity($merchant);
        $receivedOrdersCount = $this->countOrdersByStatuses($merchant, [
            OrderStatus::Submitted,
            OrderStatus::Accepted,
            OrderStatus::PartiallyAccepted,
            OrderStatus::Rejected,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::PickupPending,
            OrderStatus::Completed,
            OrderStatus::Cancelled,
        ]);
        $acceptedOrdersCount = $this->countOrdersByStatuses($merchant, [
            OrderStatus::Accepted,
            OrderStatus::PartiallyAccepted,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::PickupPending,
            OrderStatus::Completed,
        ]);
        $rejectedOrdersCount = $this->countOrdersByStatuses($merchant, [OrderStatus::Rejected]);
        $overdueOrdersCount = $this->countOverdueOrders($merchant, $overdueCutoff);
        $cancelledOrdersCount = $this->countCancelledOrders($merchant);
        $incidentsCount = $this->countIncidents($merchant);
        $openIncidentsCount = $this->countIncidents($merchant, [IncidentStatus::Open, IncidentStatus::InProgress]);
        $paymentRemindersCount = $this->countPaymentReminders($merchant);
        $adminActionsCount = $this->countAdminActions($merchant);

        return new AdminMerchantOpsJournalOutput(
            receivedOrdersCount: $receivedOrdersCount,
            acceptedOrdersCount: $acceptedOrdersCount,
            rejectedOrdersCount: $rejectedOrdersCount,
            averageResponseMinutes: $this->averageResponseMinutes($merchant),
            overdueOrdersCount: $overdueOrdersCount,
            cancelledOrdersCount: $cancelledOrdersCount,
            incidentsCount: $incidentsCount,
            openIncidentsCount: $openIncidentsCount,
            paymentRemindersCount: $paymentRemindersCount,
            adminActionsCount: $adminActionsCount,
            healthStatus: $this->healthStatus(
                overdueOrdersCount: $overdueOrdersCount,
                cancelledOrdersCount: $cancelledOrdersCount,
                rejectedOrdersCount: $rejectedOrdersCount,
                incidentsCount: $incidentsCount,
                openIncidentsCount: $openIncidentsCount,
                paymentRemindersCount: $paymentRemindersCount,
            ),
            lastActivityAt: $lastActivity['created_at']?->format(\DateTimeInterface::ATOM),
            lastActivityStatus: $lastActivity['status'],
        );
    }

    /**
     * @param list<OrderStatus> $statuses
     */
    private function countOrdersByStatuses(User $merchant, array $statuses): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(orderEntity.id)')
            ->from(Order::class, 'orderEntity')
            ->join('orderEntity.shop', 'shop')
            ->andWhere('IDENTITY(shop.owner) = :merchantId')
            ->andWhere('orderEntity.status IN (:statuses)')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->setParameter('statuses', array_map(static fn (OrderStatus $status): string => $status->value, $statuses))
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countOverdueOrders(User $merchant, \DateTimeImmutable $now): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(orderEntity.id)')
            ->from(Order::class, 'orderEntity')
            ->join('orderEntity.shop', 'shop')
            ->join('orderEntity.pickupSlot', 'slot')
            ->andWhere('IDENTITY(shop.owner) = :merchantId')
            ->andWhere('orderEntity.status IN (:statuses)')
            ->andWhere('slot.endsAt < :now')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->setParameter('statuses', [
                OrderStatus::Submitted->value,
                OrderStatus::Accepted->value,
                OrderStatus::PartiallyAccepted->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
                OrderStatus::PickupPending->value,
            ])
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countCancelledOrders(User $merchant): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(orderEntity.id)')
            ->from(Order::class, 'orderEntity')
            ->join('orderEntity.shop', 'shop')
            ->andWhere('IDENTITY(shop.owner) = :merchantId')
            ->andWhere('orderEntity.status = :status')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->setParameter('status', OrderStatus::Cancelled->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<IncidentStatus>|null $statuses
     */
    private function countIncidents(User $merchant, ?array $statuses = null): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(incident.id)')
            ->from(Incident::class, 'incident')
            ->andWhere('IDENTITY(incident.merchant) = :merchantId')
            ->setParameter('merchantId', $merchant->getId(), 'uuid');

        if (null !== $statuses) {
            $queryBuilder
                ->andWhere('incident.status IN (:statuses)')
                ->setParameter('statuses', array_map(static fn (IncidentStatus $status): string => $status->value, $statuses));
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function countPaymentReminders(User $merchant): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(reminder.id)')
            ->from(SubscriptionPaymentReminder::class, 'reminder')
            ->andWhere('IDENTITY(reminder.merchant) = :merchantId')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countAdminActions(User $merchant): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(log.id)')
            ->from(AdminAuditLog::class, 'log')
            ->andWhere('log.resourceType = :resourceType')
            ->andWhere('log.resourceId = :resourceId')
            ->setParameter('resourceType', 'merchant')
            ->setParameter('resourceId', $merchant->getId()->toRfc4122())
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function averageResponseMinutes(User $merchant): ?int
    {
        /** @var list<array{order_id: string, status: OrderStatus|string, created_at: \DateTimeImmutable}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(log.order) AS order_id')
            ->addSelect('log.status AS status')
            ->addSelect('log.createdAt AS created_at')
            ->from(OrderStatusLog::class, 'log')
            ->join('log.order', 'orderEntity')
            ->join('orderEntity.shop', 'shop')
            ->andWhere('IDENTITY(shop.owner) = :merchantId')
            ->andWhere('log.status IN (:statuses)')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->setParameter('statuses', [
                OrderStatus::Submitted->value,
                OrderStatus::Accepted->value,
                OrderStatus::PartiallyAccepted->value,
                OrderStatus::Rejected->value,
            ])
            ->orderBy('log.createdAt', 'ASC')
            ->addOrderBy('log.id', 'ASC')
            ->getQuery()
            ->getResult();

        $pendingSubmittedAtByOrder = [];
        $durations = [];
        foreach ($rows as $row) {
            $orderId = (string) $row['order_id'];
            $status = $row['status'] instanceof OrderStatus ? $row['status'] : OrderStatus::from((string) $row['status']);
            if (OrderStatus::Submitted === $status) {
                $pendingSubmittedAtByOrder[$orderId] = $row['created_at'];
                continue;
            }
            if (isset($pendingSubmittedAtByOrder[$orderId])) {
                $durations[] = max(0, (int) floor(($row['created_at']->getTimestamp() - $pendingSubmittedAtByOrder[$orderId]->getTimestamp()) / 60));
                unset($pendingSubmittedAtByOrder[$orderId]);
            }
        }

        if ([] === $durations) {
            return null;
        }

        return (int) round(array_sum($durations) / \count($durations));
    }

    private function healthStatus(
        int $overdueOrdersCount,
        int $cancelledOrdersCount,
        int $rejectedOrdersCount,
        int $incidentsCount,
        int $openIncidentsCount,
        int $paymentRemindersCount,
    ): string {
        if ($overdueOrdersCount > 0 || $rejectedOrdersCount > 0 || $openIncidentsCount > 0) {
            return 'risk';
        }
        if ($cancelledOrdersCount > 0 || $incidentsCount > 0 || $paymentRemindersCount > 0) {
            return 'watch';
        }

        return 'healthy';
    }

    /**
     * @return array{status: string|null, created_at: \DateTimeImmutable|null}
     */
    private function findLastActivity(User $merchant): array
    {
        /** @var array{status: OrderStatus|string, created_at: \DateTimeImmutable}|null $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select('log.status AS status')
            ->addSelect('log.createdAt AS created_at')
            ->from(OrderStatusLog::class, 'log')
            ->join('log.order', 'orderEntity')
            ->join('orderEntity.shop', 'shop')
            ->andWhere('IDENTITY(shop.owner) = :merchantId')
            ->orderBy('log.createdAt', 'DESC')
            ->addOrderBy('log.id', 'DESC')
            ->setParameter('merchantId', $merchant->getId(), 'uuid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $row) {
            return ['status' => null, 'created_at' => null];
        }

        $status = $row['status'] instanceof OrderStatus ? $row['status']->value : (string) $row['status'];

        return ['status' => $status, 'created_at' => $row['created_at']];
    }
}
