<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerNotificationOutput;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, CustomerNotificationOutput>
 */
final readonly class CustomerNotificationMarkReadProcessor implements ProcessorInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerNotificationOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $notificationId = (string) ($uriVariables['id'] ?? '');
        $notification = $this->notificationRepository->find($notificationId);

        // Return 404 rather than 403 to avoid leaking notification existence.
        if (!$notification instanceof Notification || !$notification->getUser()->getId()->equals($user->getId())) {
            throw new NotFoundHttpException('NOTIFICATION_NOT_FOUND');
        }

        $notification->markRead();
        $this->entityManager->flush();

        return new CustomerNotificationOutput(
            id: $notification->getId()->toRfc4122(),
            orderId: $notification->getOrder()?->getId()->toRfc4122(),
            titleFr: $notification->getTitleFr(),
            titleAr: $notification->getTitleAr(),
            bodyFr: $notification->getBodyFr(),
            bodyAr: $notification->getBodyAr(),
            isRead: $notification->isRead(),
            createdAt: $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
