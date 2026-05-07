<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\StoreThemeCacheSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class StoreThemeCacheSubscriberTest extends TestCase
{
    public function testItSetsCacheHeaderForSuccessfulThemeResponse(): void
    {
        $response = new Response(status: Response::HTTP_OK);
        $event = $this->responseEventForThemeRoute($response);

        (new StoreThemeCacheSubscriber())->onKernelResponse($event);

        self::assertTrue($response->headers->getCacheControlDirective('public'));
        self::assertSame('300', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testItDoesNotSetCacheHeaderForErrorThemeResponse(): void
    {
        $response = new Response(status: Response::HTTP_NOT_FOUND);
        $event = $this->responseEventForThemeRoute($response);

        (new StoreThemeCacheSubscriber())->onKernelResponse($event);

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
    }

    private function responseEventForThemeRoute(Response $response): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/api/stores/00000000-0000-0000-0000-000000000001/theme', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }
}
