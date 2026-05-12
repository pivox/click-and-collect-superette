<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PickupSlot;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
                static fn (PickupSlot $s): bool => $s->getEndsAt() > $after && !$s->isFull(),
            ),
        );
    }
}
