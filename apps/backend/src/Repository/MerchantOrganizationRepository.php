<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantOrganization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantOrganization>
 */
class MerchantOrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantOrganization::class);
    }
}
