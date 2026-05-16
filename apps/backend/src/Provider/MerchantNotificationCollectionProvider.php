<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantNotificationItemOutput;
use App\ApiResource\MerchantNotificationListOutput;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<MerchantNotificationListOutput>
 */
final readonly class MerchantNotificationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantNotificationListOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $unreadParam = $request?->query->get('unread');
        $unreadOnly = null !== $unreadParam ? filter_var($unreadParam, \FILTER_VALIDATE_BOOLEAN) : null;

        $notifications = $this->notificationRepository->findPageForUser($merchant, $unreadOnly, $page);
        $total = $this->notificationRepository->countForUser($merchant, $unreadOnly);

        $items = array_map(
            static fn (Notification $n): MerchantNotificationItemOutput => MerchantNotificationItemOutput::fromEntity($n),
            $notifications,
        );

        return new MerchantNotificationListOutput(
            id: $merchant->getId()->toRfc4122(),
            items: $items,
            total: $total,
            page: $page,
        );
    }
}
