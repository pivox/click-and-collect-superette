<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ClientLogInput;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<ClientLogInput, null>
 */
final readonly class ClientLogProcessor implements ProcessorInterface
{
    private const FORBIDDEN_CONTEXT_KEYS = [
        'password', 'token', 'jwt', 'secret', 'otp', 'authorization',
        'refreshtoken', 'refresh_token', 'apikey', 'api_key',
    ];

    private const MAX_CONTEXT_KEYS = 20;

    public function __construct(
        #[Autowire(service: 'monolog.logger.front')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof ClientLogInput) {
            throw new \InvalidArgumentException('ClientLogInput expected.');
        }

        $logContext = $this->buildLogContext($data);

        match ($data->level) {
            'debug' => $this->logger->debug($data->message ?? '', $logContext),
            'info' => $this->logger->info($data->message ?? '', $logContext),
            'warning' => $this->logger->warning($data->message ?? '', $logContext),
            default => $this->logger->error($data->message ?? '', $logContext),
        };

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLogContext(ClientLogInput $data): array
    {
        $ctx = [
            'front_event' => $data->event,
            'front_url' => $data->url,
            'app_version' => $data->appVersion,
            'environment' => $data->environment,
            'created_at' => $data->createdAt,
        ];

        if (\is_array($data->context)) {
            foreach ($this->sanitizeContext($data->context) as $key => $value) {
                $ctx['front_'.$key] = $value;
            }
        }

        return $ctx;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        $count = 0;

        foreach ($context as $key => $value) {
            if ($count >= self::MAX_CONTEXT_KEYS) {
                break;
            }
            if (\in_array(strtolower($key), self::FORBIDDEN_CONTEXT_KEYS, true)) {
                continue;
            }
            $sanitized[$key] = $value;
            ++$count;
        }

        return $sanitized;
    }
}
