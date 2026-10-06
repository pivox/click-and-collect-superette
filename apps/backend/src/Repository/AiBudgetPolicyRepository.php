<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiBudgetPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiBudgetPolicy>
 */
class AiBudgetPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiBudgetPolicy::class);
    }

    public function findDefault(): ?AiBudgetPolicy
    {
        return $this->find(AiBudgetPolicy::DEFAULT_ID);
    }
}
