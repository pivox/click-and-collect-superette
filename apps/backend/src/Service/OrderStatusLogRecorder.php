<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\OrderStatusActorType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderStatusLogRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
        // MERCHANT-TEAM-005: nullable so plain unit constructions keep working;
        // in that case unattributed transitions resolve to `system`.
        private ?Security $security = null,
    ) {
    }

    /**
     * The transition author is the explicit actor when given, otherwise the
     * authenticated user (typed by role), otherwise `system` — which covers
     * every Messenger handler, command and scheduled expiration.
     */
    public function record(
        Order $order,
        OrderStatus $status,
        ?string $note = null,
        ?User $actorUser = null,
        ?OrderStatusActorType $actorType = null,
    ): OrderStatusLog {
        $orderId = $order->getId()->toRfc4122();
        $fromStatus = $this->resolveOriginalStatus($order);

        if (null === $actorUser && null === $actorType) {
            [$actorUser, $actorType] = $this->resolveActorFromSecurity();
        }
        $actorType ??= null !== $actorUser ? self::actorTypeFor($actorUser) : OrderStatusActorType::System;

        $this->logger->debug('order.status_change.start', [
            'order_id' => $orderId,
            'from_status' => $fromStatus?->value,
            'to_status' => $status->value,
        ]);

        $log = new OrderStatusLog($order, $status, $note, $actorUser, $actorType);
        $this->entityManager->persist($log);
        $this->logger->info('order.status_changed', [
            'order_id' => $orderId,
            'store_id' => $order->getShop()->getId()->toRfc4122(),
            'from_status' => $fromStatus?->value,
            'to_status' => $status->value,
            'actor_user_id' => $actorUser?->getId()->toRfc4122(),
            'actor_type' => $actorType->value,
        ]);

        return $log;
    }

    /**
     * @return array{0: ?User, 1: ?OrderStatusActorType}
     */
    private function resolveActorFromSecurity(): array
    {
        $user = $this->security?->getUser();
        if (!$user instanceof User) {
            return [null, OrderStatusActorType::System];
        }

        return [$user, self::actorTypeFor($user)];
    }

    private static function actorTypeFor(User $user): OrderStatusActorType
    {
        $roles = $user->getRoles();

        return match (true) {
            \in_array('ROLE_ADMIN', $roles, true) => OrderStatusActorType::Admin,
            \in_array('ROLE_MERCHANT', $roles, true) => OrderStatusActorType::Merchant,
            default => OrderStatusActorType::Customer,
        };
    }

    private function resolveOriginalStatus(Order $order): ?OrderStatus
    {
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($order);
        $status = $originalData['status'] ?? null;

        return $status instanceof OrderStatus ? $status : null;
    }
}
