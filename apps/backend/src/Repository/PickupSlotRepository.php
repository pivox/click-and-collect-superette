<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PickupSlot;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PickupSlot>
 */
class PickupSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickupSlot::class);
    }

    /**
     * Returns all slots for a shop, ordered by start time.
     *
     * @return list<PickupSlot>
     */
    public function findForShop(Shop $shop): array
    {
        return $this->findBy(
            ['shop' => $shop],
            ['startsAt' => 'ASC'],
        );
    }

    public function findOneForShop(Shop $shop, string $slotId): ?PickupSlot
    {
        return $this->findOneBy([
            'id' => $slotId,
            'shop' => $shop,
        ]);
    }

    public function findOneForShopAndRange(
        Shop $shop,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
    ): ?PickupSlot {
        return $this->createQueryBuilder('slot')
            ->andWhere('IDENTITY(slot.shop) = :shopId')
            ->andWhere('slot.startsAt = :startsAt')
            ->andWhere('slot.endsAt = :endsAt')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('startsAt', $startsAt, Types::DATETIME_IMMUTABLE)
            ->setParameter('endsAt', $endsAt, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<PickupSlot>
     */
    public function findForShopBetweenStartsAt(
        Shop $shop,
        \DateTimeImmutable $startsAtInclusive,
        \DateTimeImmutable $startsAtExclusive,
    ): array {
        return $this->createQueryBuilder('slot')
            ->andWhere('IDENTITY(slot.shop) = :shopId')
            ->andWhere('slot.startsAt >= :startsAtInclusive')
            ->andWhere('slot.startsAt < :startsAtExclusive')
            ->orderBy('slot.startsAt', 'ASC')
            ->setParameter('shopId', $shop->getId(), 'uuid')
            ->setParameter('startsAtInclusive', $startsAtInclusive, Types::DATETIME_IMMUTABLE)
            ->setParameter('startsAtExclusive', $startsAtExclusive, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();
    }

    public function hasActiveOverlapForShop(
        Shop $shop,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        ?PickupSlot $excludeSlot = null,
    ): bool {
        $slots = $this->findBy(
            ['shop' => $shop, 'isActive' => true],
            ['startsAt' => 'ASC'],
        );

        foreach ($slots as $slot) {
            if (null !== $excludeSlot && $slot->getId()->equals($excludeSlot->getId())) {
                continue;
            }

            if ($slot->getStartsAt() < $endsAt && $slot->getEndsAt() > $startsAt) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns active, non-full, future slots for a shop, ordered by start time.
     *
     * @return list<PickupSlot>
     */
    public function findAvailableForShop(Shop $shop, ?\DateTimeImmutable $after = null): array
    {
        $after ??= new \DateTimeImmutable();

        $slots = $this->findBy(
            ['shop' => $shop, 'isActive' => true],
            ['startsAt' => 'ASC'],
        );

        return array_values(
            array_filter(
                $slots,
                static fn (PickupSlot $s): bool => $s->getStartsAt() > $after && !$s->isFull(),
            ),
        );
    }
}
