<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Push;

use App\Entity\MobileDevice;
use App\Entity\User;
use App\Enum\MobileApplication;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use App\Service\Push\ExpoPushSender;
use App\Service\Push\PushMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExpoPushSenderTest extends TestCase
{
    public function testOkTicketIsSuccess(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse(
            (string) json_encode(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]),
            ['http_code' => 200],
        ));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isSuccess());
    }

    public function testRequestBodyContainsTokenPayloadAndCollapseId(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse(
            (string) json_encode(['data' => [['status' => 'ok']]]),
            ['http_code' => 200],
        ));

        $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame(ExpoPushSender::ENDPOINT, $requests[0]['url']);
        $body = json_decode($requests[0]['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertCount(1, $body);
        self::assertSame('ExponentPushToken[unit-test]', $body[0]['to']);
        self::assertSame('Titre', $body[0]['title']);
        self::assertSame('notif-1', $body[0]['collapseId']);
        self::assertSame(['type' => 'order_accepted'], $body[0]['data']);
    }

    public function testDeviceNotRegisteredIsPermanentAndUnregistersDevice(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse(
            (string) json_encode(['data' => [[
                'status' => 'error',
                'message' => 'device is not registered',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]]]),
            ['http_code' => 200],
        ));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isPermanentFailure());
        self::assertTrue($result->deviceUnregistered);
        self::assertSame('DeviceNotRegistered', $result->reason);
    }

    public function testMessageTooBigIsPermanentWithoutUnregistering(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse(
            (string) json_encode(['data' => [[
                'status' => 'error',
                'details' => ['error' => 'MessageTooBig'],
            ]]]),
            ['http_code' => 200],
        ));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isPermanentFailure());
        self::assertFalse($result->deviceUnregistered);
    }

    public function testServerErrorIsTemporaryFailure(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse('upstream down', ['http_code' => 503]));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isTemporaryFailure());
        self::assertSame('http_503', $result->reason);
    }

    public function testRateLimitIsTemporaryFailure(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse('slow down', ['http_code' => 429]));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isTemporaryFailure());
    }

    public function testBadRequestIsPermanentWithoutUnregistering(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse('bad payload', ['http_code' => 400]));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isPermanentFailure());
        self::assertFalse($result->deviceUnregistered);
    }

    public function testTransportErrorIsTemporaryFailure(): void
    {
        $requests = [];
        $sender = $this->createSender($requests, new MockResponse('', ['error' => 'network unreachable']));

        $result = $sender->send($this->createPushMessage(), $this->createDevice());

        self::assertTrue($result->isTemporaryFailure());
        self::assertSame('transport_error', $result->reason);
    }

    /**
     * @param list<array{method: string, url: string, body: string}> $requests
     */
    private function createSender(array &$requests, MockResponse $response): ExpoPushSender
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $response): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => (string) ($options['body'] ?? ''),
            ];

            return $response;
        });

        return new ExpoPushSender($httpClient, new NullLogger());
    }

    private function createPushMessage(): PushMessage
    {
        return new PushMessage(
            title: 'Titre',
            body: 'Corps',
            data: ['type' => 'order_accepted'],
            collapseId: 'notif-1',
        );
    }

    private function createDevice(): MobileDevice
    {
        return new MobileDevice(
            user: (new User())->setEmail('push-unit@example.test')->setPassword('x')->setName('Push Unit'),
            application: MobileApplication::Client,
            platform: MobilePlatform::Android,
            provider: MobilePushProvider::Expo,
            pushToken: 'ExponentPushToken[unit-test]',
        );
    }
}
