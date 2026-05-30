<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Monolog\CorrelationIdProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class CorrelationIdSubscriber implements EventSubscriberInterface
{
    public function __construct(private CorrelationIdProcessor $correlationIdProcessor)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 20]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $id = $event->getRequest()->headers->get('X-Client-Request-Id');
        $this->correlationIdProcessor->setCorrelationId($id ?: null);
    }
}
