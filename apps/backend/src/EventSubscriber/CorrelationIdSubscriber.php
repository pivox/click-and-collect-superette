<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Monolog\CorrelationIdProcessor;
use App\Service\RequestIdResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Correlation id lifecycle (#617):
 * - kernel.request (high priority): keep a valid X-Client-Request-Id or generate
 *   a server UUID v4, store it on the request and feed the Monolog processor.
 * - kernel.response: expose the effective id in the X-Request-Id response header
 *   for every response, success and error alike.
 */
final readonly class CorrelationIdSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ATTRIBUTE = '_request_id';
    public const RESPONSE_HEADER = 'X-Request-Id';
    private const CLIENT_HEADER = 'X-Client-Request-Id';

    public function __construct(
        private CorrelationIdProcessor $correlationIdProcessor,
        private RequestIdResolver $requestIdResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 255],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $clientProvidedId = $request->headers->get(self::CLIENT_HEADER);
        $requestId = $this->requestIdResolver->resolve('' !== $clientProvidedId ? $clientProvidedId : null);

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $requestId);
        $this->correlationIdProcessor->setCorrelationId($requestId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $event->getRequest()->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!\is_string($requestId) || '' === $requestId) {
            return;
        }

        $event->getResponse()->headers->set(self::RESPONSE_HEADER, $requestId);
    }
}
