<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MobileDevice;
use App\Entity\User;
use App\Enum\MobileApplication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MobileDevice> */
final class MobileDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobileDevice::class);
    }

    public function findOneByTokenHash(string $hash): ?MobileDevice
    {
        return $this->findOneBy(['pushTokenHash' => $hash]);
    }

    /**
     * Active devices of a user for one application: enabled, never revoked.
     *
     * @return list<MobileDevice>
     */
    public function findActiveForUserAndApplication(User $user, MobileApplication $application): array
    {
        /* @var list<MobileDevice> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :userId')
            ->andWhere('d.application = :application')
            ->andWhere('d.enabled = :enabled')
            ->andWhere('d.revokedAt IS NULL')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->setParameter('application', $application->value)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Physical purge for account deletion (#620 decision 11): the soft delete
     * of the User keeps the row, so the FK CASCADE never fires — push tokens
     * must be removed explicitly.
     */
    public function deleteAllForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('d')
            ->delete()
            ->andWhere('d.user = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
