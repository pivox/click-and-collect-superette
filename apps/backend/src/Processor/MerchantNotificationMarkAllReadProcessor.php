<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<null, null>
 */
final readonly class MerchantNotificationMarkAllReadProcessor implements ProcessorInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        $this->notificationRepository->markAllReadForUser($merchant);

        return null;
    }
}
