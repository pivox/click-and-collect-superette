<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreThemeCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ('GET' !== $request->getMethod() || !preg_match('#^/api/stores/[^/]+/theme$#', $request->getPathInfo())) {
            return;
        }

        if (!$response->isSuccessful()) {
            return;
        }

        $response->headers->set('Cache-Control', 'public, max-age=300');
    }
}
