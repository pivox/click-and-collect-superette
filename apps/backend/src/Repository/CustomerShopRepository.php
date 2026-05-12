<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerShop;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\CustomerShopStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerShop>
 */
class CustomerShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerShop::class);
    }

    public function findOneByCustomerAndShop(User $customer, Shop $shop): ?CustomerShop
    {
        return $this->findOneBy(['customer' => $customer, 'shop' => $shop]);
    }

    /**
     * Returns active customer-shop relations for active shops only,
     * ordered by favorites first then by last seen.
     *
     * @return list<CustomerShop>
     */
    public function findActiveByCustomer(User $customer): array
    {
        $relations = $this->findBy(
            ['customer' => $customer, 'status' => CustomerShopStatus::Active],
            ['isFavorite' => 'DESC', 'lastSeenAt' => 'DESC'],
        );

        /* @var list<CustomerShop> */
        return array_values(
            array_filter($relations, static fn (CustomerShop $cs): bool => $cs->getShop()->isActive()),
        );
    }
}
