<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomerNotificationItemOutput;
use App\ApiResource\CustomerNotificationListOutput;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<CustomerNotificationListOutput>
 */
final readonly class CustomerNotificationCollectionProvider implements ProviderInterface
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
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerNotificationListOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $unreadParam = $request?->query->get('unread');
        $unreadOnly = null !== $unreadParam ? filter_var($unreadParam, \FILTER_VALIDATE_BOOLEAN) : null;

        $notifications = $this->notificationRepository->findPageForUser($user, $unreadOnly, $page);
        $total = $this->notificationRepository->countForUser($user, $unreadOnly);

        $items = array_map(
            static fn (Notification $n): CustomerNotificationItemOutput => CustomerNotificationItemOutput::fromEntity($n),
            $notifications,
        );

        return new CustomerNotificationListOutput(
            id: $user->getId()->toRfc4122(),
            items: $items,
            total: $total,
            page: $page,
        );
    }
}
