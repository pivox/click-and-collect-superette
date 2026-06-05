<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\AdminMerchantOpsJournalOutput;
use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\User;
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

        return new AdminMerchantOpsJournalOutput(
            overdueOrdersCount: $this->countOverdueOrders($merchant, $overdueCutoff),
            cancelledOrdersCount: $this->countCancelledOrders($merchant),
            lastActivityAt: $lastActivity['created_at']?->format(\DateTimeInterface::ATOM),
            lastActivityStatus: $lastActivity['status'],
        );
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
            ->setParameter('status', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();
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
