<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\User;
use App\Enum\MerchantMembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantMembership>
 */
class MerchantMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantMembership::class);
    }

    public function findOneActiveByUser(User $user): ?MerchantMembership
    {
        return $this->findOneBy(['user' => $user, 'status' => MerchantMembershipStatus::Active]);
    }

    /**
     * @return list<MerchantMembership>
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findOneByOrganizationAndUser(MerchantOrganization $organization, User $user): ?MerchantMembership
    {
        return $this->findOneBy(['organization' => $organization, 'user' => $user]);
    }
}
