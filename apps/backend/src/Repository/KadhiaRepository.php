<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Kadhia;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Kadhia>
 */
class KadhiaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kadhia::class);
    }

    public function findDraftByCustomerAndShop(User $customer, Shop $shop): ?Kadhia
    {
        return $this->findOneBy([
            'customer' => $customer,
            'shop' => $shop,
            'status' => KadhiaStatus::Draft,
        ]);
    }
}
