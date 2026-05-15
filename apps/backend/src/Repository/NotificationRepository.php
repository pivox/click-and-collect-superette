<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function findLatestForUser(User $user, ?bool $unreadOnly = null, int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('notification')
            ->andWhere('IDENTITY(notification.user) = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->orderBy('notification.createdAt', 'DESC')
            ->addOrderBy('notification.id', 'DESC')
            ->setMaxResults($limit);

        if (true === $unreadOnly) {
            $queryBuilder
                ->andWhere('notification.read = :read')
                ->setParameter('read', false);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
