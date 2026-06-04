<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\AdminStoreActivationChecklistOutput;
use App\ApiResource\AdminStoreActivationChecklistStepOutput;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\PickupSession;
use App\Entity\PickupSlot;
use App\Entity\PickupSlotRule;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class StoreActivationChecklistCalculator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $minimumCatalogProducts = 5,
    ) {
    }

    public function calculate(Shop $shop): AdminStoreActivationChecklistOutput
    {
        $catalogProducts = $this->countCatalogProducts($shop);
        $steps = [
            new AdminStoreActivationChecklistStepOutput(
                key: 'merchant_active',
                label: 'Marchand actif',
                completed: $this->hasActiveMerchant($shop),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'store_active',
                label: 'Supérette active',
                completed: $shop->isActive() && null === $shop->getArchivedAt(),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'opening_hours',
                label: 'Horaires renseignés',
                completed: $this->hasOpeningHours($shop),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'pickup_slots',
                label: 'Créneaux créés',
                completed: $this->hasPickupSlots($shop),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'catalog_minimum',
                label: 'Catalogue minimum',
                completed: $catalogProducts >= $this->minimumCatalogProducts,
                required: true,
                currentValue: $catalogProducts,
                targetValue: $this->minimumCatalogProducts,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'qr_code',
                label: 'QR code accessible',
                completed: $shop->isActive() && null === $shop->getArchivedAt() && '' !== trim($shop->getQrCodeToken()),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'test_order',
                label: 'Commande test passée',
                completed: $this->hasTestOrder($shop),
                required: true,
            ),
            new AdminStoreActivationChecklistStepOutput(
                key: 'test_pickup',
                label: 'Retrait test validé',
                completed: $this->hasValidatedPickup($shop),
                required: true,
            ),
        ];

        $requiredTotal = \count(array_filter($steps, static fn (AdminStoreActivationChecklistStepOutput $step): bool => $step->required));
        $requiredCompleted = \count(array_filter($steps, static fn (AdminStoreActivationChecklistStepOutput $step): bool => $step->required && $step->completed));

        return new AdminStoreActivationChecklistOutput(
            storeId: $shop->getId()->toRfc4122(),
            storeName: $shop->getName(),
            ready: $requiredCompleted === $requiredTotal,
            minimumCatalogProducts: $this->minimumCatalogProducts,
            requiredCompletedCount: $requiredCompleted,
            requiredTotalCount: $requiredTotal,
            steps: $steps,
        );
    }

    private function hasActiveMerchant(Shop $shop): bool
    {
        $owner = $shop->getOwner();

        return null !== $owner && $owner->isActive() && \in_array('ROLE_MERCHANT', $owner->getRoles(), true);
    }

    private function hasOpeningHours(Shop $shop): bool
    {
        $openingHours = $shop->getOpeningHours();
        if (!\is_array($openingHours)) {
            return false;
        }

        $weekly = $openingHours['weekly'] ?? null;
        if (!\is_array($weekly)) {
            return false;
        }

        foreach ($weekly as $daySlots) {
            if (!\is_array($daySlots)) {
                continue;
            }

            foreach ($daySlots as $slot) {
                if (
                    \is_array($slot)
                    && isset($slot['start'], $slot['end'])
                    && '' !== trim((string) $slot['start'])
                    && '' !== trim((string) $slot['end'])
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasPickupSlots(Shop $shop): bool
    {
        $activeRuleCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(rule.id)')
            ->from(PickupSlotRule::class, 'rule')
            ->andWhere('IDENTITY(rule.shop) = :shopId')
            ->andWhere('rule.isActive = true')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeRuleCount > 0) {
            return true;
        }

        $futureSlotCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(slot.id)')
            ->from(PickupSlot::class, 'slot')
            ->andWhere('IDENTITY(slot.shop) = :shopId')
            ->andWhere('slot.isActive = true')
            ->andWhere('slot.startsAt > :now')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        return $futureSlotCount > 0;
    }

    private function countCatalogProducts(Shop $shop): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(product.id)')
            ->from(MerchantProduct::class, 'product')
            ->andWhere('IDENTITY(product.shop) = :shopId')
            ->andWhere('product.isVisible = true')
            ->andWhere('product.isAvailable = true')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function hasTestOrder(Shop $shop): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(orderEntity.id)')
            ->from(Order::class, 'orderEntity')
            ->andWhere('IDENTITY(orderEntity.shop) = :shopId')
            ->andWhere('orderEntity.status IN (:statuses)')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('statuses', [
                OrderStatus::Submitted,
                OrderStatus::Accepted,
                OrderStatus::PartiallyAccepted,
                OrderStatus::Rejected,
                OrderStatus::Preparing,
                OrderStatus::Ready,
                OrderStatus::PickupPending,
                OrderStatus::Completed,
                OrderStatus::Cancelled,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function hasValidatedPickup(Shop $shop): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(pickupSession.id)')
            ->from(PickupSession::class, 'pickupSession')
            ->join('pickupSession.order', 'orderEntity')
            ->andWhere('IDENTITY(orderEntity.shop) = :shopId')
            ->andWhere('orderEntity.status = :completed')
            ->andWhere('pickupSession.used = true')
            ->andWhere('pickupSession.merchantConfirmedAt IS NOT NULL')
            ->andWhere('pickupSession.customerConfirmedAt IS NOT NULL')
            ->andWhere('pickupSession.forceCompletedByMerchant = false')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('completed', OrderStatus::Completed)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
