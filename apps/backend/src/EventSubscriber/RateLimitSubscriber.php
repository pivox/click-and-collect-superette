<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\RateLimit\RateLimiterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rate limiting of sensitive public endpoints (#622).
 *
 * Runs on kernel.request at priority 20: after routing (32) and the
 * correlation id subscriber (255), before the security firewall (8) so an
 * abusive client is throttled before any authentication work happens.
 *
 * Policies are configured in services.yaml (`app.rate_limit.policies`) and the
 * whole feature can be disabled via RATE_LIMIT_ENABLED (default: enabled,
 * disabled in the test environment to keep the existing functional suite
 * unaffected).
 *
 * Known limit: Request::getClientIp() only returns the real client IP when
 * `trusted_proxies` is configured; behind an unconfigured proxy every client
 * shares the proxy IP (same caveat as AdminAuditLog.ipAddress).
 */
final readonly class RateLimitSubscriber implements EventSubscriberInterface
{
    public const DETAIL_CODE = 'RATE_LIMITED';

    /**
     * @param array<string, array{method: string, path?: string, path_prefix?: string, by: string, limit: int, window: int}> $policies
     */
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private bool $enabled,
        private array $policies,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 20],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $retryAfterSeconds = 0;

        foreach ($this->policies as $limiterName => $policy) {
            if (!$this->matches($request, $policy)) {
                continue;
            }

            $decision = $this->rateLimiter->consume(
                $limiterName,
                $this->resolveIdentifier($request, $policy['by']),
                $policy['limit'],
                $policy['window'],
            );

            if (!$decision->accepted) {
                $retryAfterSeconds = max($retryAfterSeconds, $decision->retryAfterSeconds);
            }
        }

        if ($retryAfterSeconds > 0) {
            $event->setResponse($this->buildTooManyRequestsResponse($retryAfterSeconds));
        }
    }

    /**
     * @param array{method: string, path?: string, path_prefix?: string, by: string, limit: int, window: int} $policy
     */
    private function matches(Request $request, array $policy): bool
    {
        if ($request->getMethod() !== $policy['method']) {
            return false;
        }

        $pathInfo = $request->getPathInfo();
        if (isset($policy['path'])) {
            return $pathInfo === $policy['path'];
        }

        if (isset($policy['path_prefix'])) {
            return str_starts_with($pathInfo, $policy['path_prefix']);
        }

        return false;
    }

    private function resolveIdentifier(Request $request, string $by): string
    {
        $ip = $request->getClientIp() ?? 'unknown';

        if ('ip_email' === $by) {
            return $ip.'|'.$this->extractEmail($request);
        }

        return $ip;
    }

    /**
     * Best-effort extraction of the email from a JSON payload. A missing or
     * malformed body yields an empty email: the limiter then behaves per IP.
     */
    private function extractEmail(Request $request): string
    {
        $content = $request->getContent();
        if ('' === $content) {
            return '';
        }

        try {
            $payload = json_decode($content, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        if (!\is_array($payload) || !isset($payload['email']) || !\is_string($payload['email'])) {
            return '';
        }

        return mb_strtolower(trim($payload['email']));
    }

    private function buildTooManyRequestsResponse(int $retryAfterSeconds): JsonResponse
    {
        $response = new JsonResponse(
            [
                'type' => '/errors/429',
                'title' => 'Too Many Requests',
                'status' => Response::HTTP_TOO_MANY_REQUESTS,
                'detail' => self::DETAIL_CODE,
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retryAfterSeconds],
        );
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
