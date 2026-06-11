<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantCrmContact;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantCrmContact>
 */
class MerchantCrmContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantCrmContact::class);
    }

    /**
     * @return list<MerchantCrmContact>
     */
    public function findByMerchant(User $merchant): array
    {
        /* @var list<MerchantCrmContact> */
        return $this->findBy(
            ['merchant' => $merchant],
            ['contactedAt' => 'DESC', 'createdAt' => 'DESC'],
        );
    }
}
