<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\PickupSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PickupSession>
 */
class PickupSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickupSession::class);
    }

    public function findOneByOrder(Order $order): ?PickupSession
    {
        return $this->findOneBy(['order' => $order]);
    }

    public function findOneByToken(Uuid $token): ?PickupSession
    {
        return $this->createQueryBuilder('pickupSession')
            ->select('pickupSession', 'orders', 'shop', 'customer', 'line', 'merchantProduct', 'productReference')
            ->innerJoin('pickupSession.order', 'orders')
            ->innerJoin('orders.shop', 'shop')
            ->innerJoin('orders.customer', 'customer')
            ->leftJoin('orders.lines', 'line')
            ->leftJoin('line.merchantProduct', 'merchantProduct')
            ->leftJoin('merchantProduct.productReference', 'productReference')
            ->andWhere('pickupSession.token = :token')
            ->setParameter('token', $token, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByIdWithOrder(string $id): ?PickupSession
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->createQueryBuilder('pickupSession')
            ->select('pickupSession', 'orders', 'shop', 'customer')
            ->innerJoin('pickupSession.order', 'orders')
            ->innerJoin('orders.shop', 'shop')
            ->innerJoin('orders.customer', 'customer')
            ->andWhere('pickupSession.id = :id')
            ->setParameter('id', Uuid::fromString($id), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
