<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantNotificationOutput;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<null, MerchantNotificationOutput>
 */
final readonly class MerchantNotificationMarkReadProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantNotificationOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        $notificationId = (string) ($uriVariables['id'] ?? '');
        $notification = $this->notificationRepository->find($notificationId);

        if (!$notification instanceof Notification || !$notification->getUser()->getId()->equals($merchant->getId())) {
            throw new NotFoundHttpException('NOTIFICATION_NOT_FOUND');
        }

        $notification->markRead();
        $this->entityManager->flush();

        return new MerchantNotificationOutput(
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
