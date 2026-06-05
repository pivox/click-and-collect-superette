<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ProductUnit;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenAiMerchantCatalogPhotoImportExtractor implements MerchantCatalogPhotoImportExtractorInterface
{
    private const API_BASE_URL = 'https://api.openai.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $catalogPhotoImportOpenAiApiKey,
        private string $catalogPhotoImportOpenAiModel,
    ) {
    }

    public function extract(UploadedFile $photo, string $sourceType): array
    {
        if ('' === trim($this->catalogPhotoImportOpenAiApiKey)) {
            throw new MerchantCatalogPhotoImportUnavailableException('CATALOG_PHOTO_IMPORT_AI_UNCONFIGURED');
        }

        $content = file_get_contents((string) $photo->getRealPath());
        if (false === $content) {
            throw new MerchantCatalogPhotoImportUnavailableException('CATALOG_PHOTO_IMPORT_FILE_UNREADABLE');
        }

        $response = $this->httpClient->request('POST', self::API_BASE_URL.'/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->catalogPhotoImportOpenAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->catalogPhotoImportOpenAiModel,
                'temperature' => 0,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Tu extrais des produits de supérette en Tunisie depuis une photo. Retourne uniquement les produits lisibles et actionnables pour un marchand Kadhia. Prix en TND si visible, sinon null.',
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => json_encode([
                                    'market' => 'TN',
                                    'currency' => 'TND',
                                    'source_type' => $sourceType,
                                ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => 'data:'.$photo->getMimeType().';base64,'.base64_encode($content),
                            ],
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'merchant_catalog_photo_import',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ],
            'timeout' => 60,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        if (($payload['error'] ?? null) || !isset($payload['output'])) {
            $errorDetail = \is_array($payload['error'] ?? null)
                ? ($payload['error']['message'] ?? json_encode($payload['error']))
                : json_encode($payload);
            throw new MerchantCatalogPhotoImportUnavailableException('CATALOG_PHOTO_IMPORT_AI_FAILED: '.$errorDetail);
        }

        return $this->fromResponsePayload($payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<MerchantCatalogPhotoDetectedProduct>
     */
    private function fromResponsePayload(array $payload): array
    {
        $text = $this->extractOutputText($payload);
        $decoded = json_decode($text, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded) || !\is_array($decoded['products'] ?? null)) {
            throw new MerchantCatalogPhotoImportUnavailableException('CATALOG_PHOTO_IMPORT_AI_INVALID_JSON');
        }

        $products = [];
        foreach ($decoded['products'] as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $nameFr = trim((string) ($item['name_fr'] ?? ''));
            if ('' === $nameFr) {
                continue;
            }

            $unit = \is_string($item['unit'] ?? null) ? ProductUnit::tryFrom($item['unit']) : null;
            $products[] = new MerchantCatalogPhotoDetectedProduct(
                line: $index + 1,
                nameFr: $nameFr,
                brand: $this->optionalString($item['brand'] ?? null),
                volume: $this->optionalDecimal($item['volume'] ?? null),
                unit: $unit,
                barcode: $this->optionalString($item['barcode'] ?? null),
                suggestedPriceTnd: $this->optionalDecimal($item['suggested_price_tnd'] ?? null),
                confidence: $this->optionalDecimal($item['confidence'] ?? null),
            );
        }

        return $products;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOutputText(array $payload): string
    {
        foreach (($payload['output'] ?? []) as $outputItem) {
            if (!\is_array($outputItem)) {
                continue;
            }
            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (\is_array($contentItem) && \is_string($contentItem['text'] ?? null)) {
                    return $contentItem['text'];
                }
            }
        }

        throw new MerchantCatalogPhotoImportUnavailableException('CATALOG_PHOTO_IMPORT_AI_TEXT_MISSING');
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'products' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'name_fr' => ['type' => 'string', 'maxLength' => 255],
                            'brand' => ['type' => ['string', 'null'], 'maxLength' => 160],
                            'volume' => ['type' => ['string', 'null']],
                            'unit' => ['type' => ['string', 'null'], 'enum' => ['litre', 'millilitre', 'kilogramme', 'gramme', 'piece', 'paquet', null]],
                            'barcode' => ['type' => ['string', 'null'], 'maxLength' => 14],
                            'suggested_price_tnd' => ['type' => ['string', 'null']],
                            'confidence' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['name_fr', 'brand', 'volume', 'unit', 'barcode', 'suggested_price_tnd', 'confidence'],
                    ],
                ],
            ],
            'required' => ['products'],
        ];
    }

    private function optionalString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function optionalDecimal(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return null;
        }

        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return null;
        }

        return '' === $value ? null : bcadd($value, '0', 3);
    }
}
