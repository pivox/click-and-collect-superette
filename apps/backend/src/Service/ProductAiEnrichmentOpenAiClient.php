<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ProductAiEnrichmentOpenAiClient
{
    private const API_BASE_URL = 'https://api.openai.com';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function createBatch(string $apiKey, string $jsonl): string
    {
        $fileId = $this->uploadBatchFile($apiKey, $jsonl);
        $response = $this->httpClient->request('POST', self::API_BASE_URL.'/v1/batches', [
            'headers' => $this->jsonHeaders($apiKey),
            'json' => [
                'input_file_id' => $fileId,
                'endpoint' => '/v1/responses',
                'completion_window' => '24h',
            ],
            'timeout' => 30,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        if (($payload['error'] ?? null) || !\is_string($payload['id'] ?? null)) {
            throw new \RuntimeException('OPENAI_BATCH_CREATE_FAILED');
        }

        return $payload['id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveBatch(string $apiKey, string $batchId): array
    {
        $response = $this->httpClient->request('GET', self::API_BASE_URL.'/v1/batches/'.$batchId, [
            'headers' => $this->jsonHeaders($apiKey),
            'timeout' => 30,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function downloadOutputLines(string $apiKey, string $fileId): array
    {
        $response = $this->httpClient->request('GET', self::API_BASE_URL.'/v1/files/'.$fileId.'/content', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
            ],
            'timeout' => 60,
        ]);

        $content = $response->getContent(false);
        $lines = [];
        foreach (preg_split('/\r?\n/', trim($content)) ?: [] as $line) {
            if ('' === trim($line)) {
                continue;
            }

            $decoded = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            if (\is_array($decoded)) {
                /* @var array<string, mixed> $decoded */
                $lines[] = $decoded;
            }
        }

        return $lines;
    }

    private function uploadBatchFile(string $apiKey, string $jsonl): string
    {
        $boundary = 'kadhia-openai-'.bin2hex(random_bytes(12));
        $body = $this->multipartBody($boundary, $jsonl);
        $response = $this->httpClient->request('POST', self::API_BASE_URL.'/v1/files', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        if (($payload['error'] ?? null) || !\is_string($payload['id'] ?? null)) {
            throw new \RuntimeException('OPENAI_FILE_UPLOAD_FAILED');
        }

        return $payload['id'];
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function multipartBody(string $boundary, string $jsonl): string
    {
        $eol = "\r\n";

        return '--'.$boundary.$eol
            .'Content-Disposition: form-data; name="purpose"'.$eol.$eol
            .'batch'.$eol
            .'--'.$boundary.$eol
            .'Content-Disposition: form-data; name="file"; filename="product-ai-enrichment.jsonl"'.$eol
            .'Content-Type: application/jsonl'.$eol.$eol
            .$jsonl.$eol
            .'--'.$boundary.'--'.$eol;
    }
}
