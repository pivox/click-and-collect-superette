<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\MobileDevice;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MOBILE-PUSH #620: Expo Push Service adapter (ADR-0007) over the Symfony
 * HttpClient already present — no new composer dependency, no SDK. The
 * backend holds no FCM/APNs credential: Expo brokers the delivery
 * (docs/mobile/push-and-links.md §4).
 *
 * Error classification (decision 6):
 * - transport error, 429, 5xx, MessageRateExceeded → temporary (Messenger retry);
 * - other 4xx, MessageTooBig, InvalidCredentials → permanent, no revocation;
 * - DeviceNotRegistered → permanent + device revocation (provider_rejected).
 *
 * Never called directly in tests (no network): mock PushSenderInterface.
 */
final readonly class ExpoPushSender implements PushSenderInterface
{
    public const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /** Expo accepts at most 100 messages per request. */
    public const MAX_BATCH_SIZE = 100;

    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'monolog.logger.notification')]
        private LoggerInterface $logger,
    ) {
    }

    public function send(PushMessage $message, MobileDevice $device): PushSendResult
    {
        $results = $this->sendBatch([[$message, $device]]);

        return $results[0];
    }

    /**
     * Sends entries chunked by MAX_BATCH_SIZE, preserving the input order of
     * the results.
     *
     * @param list<array{PushMessage, MobileDevice}> $entries
     *
     * @return list<PushSendResult>
     */
    private function sendBatch(array $entries): array
    {
        $results = [];
        foreach (array_chunk($entries, self::MAX_BATCH_SIZE) as $chunk) {
            foreach ($this->sendChunk($chunk) as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param list<array{PushMessage, MobileDevice}> $chunk
     *
     * @return list<PushSendResult>
     */
    private function sendChunk(array $chunk): array
    {
        $body = [];
        foreach ($chunk as [$message, $device]) {
            $body[] = [
                'to' => $device->getPushToken(),
                'title' => $message->title,
                'body' => $message->body,
                'data' => $message->data,
                // collapse_key (Android) / apns-collapse-id (iOS): a retry
                // replaces the already displayed notification.
                'collapseId' => $message->collapseId,
            ];
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'json' => $body,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
            $statusCode = $response->getStatusCode();

            if (429 === $statusCode || $statusCode >= 500) {
                return $this->uniformResults($chunk, PushSendResult::temporaryFailure('http_'.$statusCode));
            }

            if ($statusCode >= 400) {
                // Malformed request: retrying the exact same payload cannot
                // succeed — permanent, but the token itself is not invalidated.
                return $this->uniformResults($chunk, PushSendResult::permanentFailure('http_'.$statusCode));
            }

            $payload = $response->toArray(false);
        } catch (HttpClientExceptionInterface $exception) {
            $this->logger->warning('notification.expo_push_transport_failed', [
                'error' => $exception->getMessage(),
            ]);

            return $this->uniformResults($chunk, PushSendResult::temporaryFailure('transport_error'));
        }

        $tickets = isset($payload['data']) && \is_array($payload['data']) ? array_values($payload['data']) : [];

        $results = [];
        foreach ($chunk as $index => $entry) {
            $ticket = $tickets[$index] ?? null;
            $results[] = \is_array($ticket)
                ? $this->classifyTicket($ticket)
                : PushSendResult::temporaryFailure('missing_ticket');
        }

        return $results;
    }

    /**
     * @param array<mixed> $ticket
     */
    private function classifyTicket(array $ticket): PushSendResult
    {
        if ('ok' === ($ticket['status'] ?? null)) {
            return PushSendResult::success();
        }

        $details = $ticket['details'] ?? null;
        $error = \is_array($details) && \is_string($details['error'] ?? null) ? $details['error'] : 'unknown_error';

        return match ($error) {
            'DeviceNotRegistered' => PushSendResult::permanentFailure($error, deviceUnregistered: true),
            'MessageTooBig', 'InvalidCredentials' => PushSendResult::permanentFailure($error),
            'MessageRateExceeded' => PushSendResult::temporaryFailure($error),
            // Unknown provider errors go through the bounded Messenger retry
            // and end up in the `failed` transport, visible via #353.
            default => PushSendResult::temporaryFailure($error),
        };
    }

    /**
     * @param list<array{PushMessage, MobileDevice}> $chunk
     *
     * @return list<PushSendResult>
     */
    private function uniformResults(array $chunk, PushSendResult $result): array
    {
        return array_fill(0, \count($chunk), $result);
    }
}
