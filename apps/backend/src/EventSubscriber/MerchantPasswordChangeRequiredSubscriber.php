<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MerchantPasswordChangeRequiredSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_PATHS = [
        'GET /api/merchant/me',
        'POST /api/merchant/first-login/change-password',
    ];

    public function __construct(private Security $security)
    {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 32],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/merchant')) {
            return;
        }
        if ('OPTIONS' === $request->getMethod()) {
            return;
        }

        $methodAndPath = $request->getMethod().' '.$path;
        if (\in_array($methodAndPath, self::ALLOWED_PATHS, true)) {
            return;
        }
        if (!$this->security->isGranted('ROLE_MERCHANT')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isPasswordChangeRequired()) {
            return;
        }

        throw new AccessDeniedHttpException('MERCHANT_PASSWORD_CHANGE_REQUIRED');
    }
}
