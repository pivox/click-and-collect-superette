<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Order;
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

    /**
     * @return list<Notification>
     */
    public function findPageForUser(User $user, ?bool $unreadOnly, int $page, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('IDENTITY(n.user) = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($perPage)
            ->setFirstResult(($page - 1) * $perPage);

        if (true === $unreadOnly) {
            $qb->andWhere('n.read = :read')->setParameter('read', false);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForUser(User $user, ?bool $unreadOnly): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('IDENTITY(n.user) = :userId')
            ->setParameter('userId', $user->getId(), 'uuid');

        if (true === $unreadOnly) {
            $qb->andWhere('n.read = :read')->setParameter('read', false);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function existsForOrderAndType(Order $order, string $type): bool
    {
        return null !== $this->findOneBy([
            'order' => $order,
            'type' => $type,
        ]);
    }

    public function markAllReadForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.read', ':true')
            ->where('IDENTITY(n.user) = :userId')
            ->andWhere('n.read = :false')
            ->setParameter('true', true)
            ->setParameter('false', false)
            ->setParameter('userId', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
