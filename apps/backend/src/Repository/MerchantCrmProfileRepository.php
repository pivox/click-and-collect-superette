<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MerchantCrmProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantCrmProfile>
 */
class MerchantCrmProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantCrmProfile::class);
    }

    public function findOneByMerchant(User $merchant): ?MerchantCrmProfile
    {
        return $this->findOneBy(['merchant' => $merchant]);
    }

    /**
     * @param list<User> $merchants
     *
     * @return array<string, MerchantCrmProfile>
     */
    public function findByMerchantsIndexed(array $merchants): array
    {
        if ([] === $merchants) {
            return [];
        }

        /** @var list<MerchantCrmProfile> $profiles */
        $profiles = $this->findBy(['merchant' => $merchants]);
        $indexed = [];
        foreach ($profiles as $profile) {
            $indexed[$profile->getMerchant()->getId()->toRfc4122()] = $profile;
        }

        return $indexed;
    }
}
