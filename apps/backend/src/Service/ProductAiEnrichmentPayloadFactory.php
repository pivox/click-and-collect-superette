<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductAiEnrichmentJob;

final class ProductAiEnrichmentPayloadFactory
{
    /**
     * @return array{custom_id: string, method: string, url: string, body: array<string, mixed>}
     */
    public function buildBatchRequest(ProductAiEnrichmentJob $job, string $model): array
    {
        $productReference = $job->getProductReference();
        $input = [
            [
                'role' => 'system',
                'content' => [[
                    'type' => 'input_text',
                    'text' => 'Tu enrichis un référentiel de produits de supérette en Tunisie. Réponds uniquement avec des valeurs structurées. Si une valeur factuelle comme EAN, prix ou TVA est incertaine, retourne null et ajoute un avertissement.',
                ]],
            ],
            [
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => json_encode([
                        'market' => 'TN',
                        'currency' => 'TND',
                        'product_reference_id' => $productReference->getId()->toRfc4122(),
                        'name_fr' => $productReference->getNameFr(),
                        'name_ar' => $productReference->getNameAr(),
                        'brand' => $productReference->getBrand()->getCanonicalName(),
                        'category' => $productReference->getCategory()->getNameFr(),
                        'volume' => $productReference->getVolume(),
                        'unit' => $productReference->getUnit()->value,
                        'barcode' => $productReference->getBarcode(),
                        'aliases' => $productReference->getAliases(),
                    ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                ]],
            ],
        ];

        $request = [
            'custom_id' => $job->getId()->toRfc4122(),
            'method' => 'POST',
            'url' => '/v1/responses',
            'body' => [
                'model' => $model,
                'input' => $input,
                'temperature' => 0,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'product_ai_enrichment',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ],
        ];

        $job->setInputPayload($request);

        return $request;
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
                'brand' => ['type' => ['string', 'null'], 'maxLength' => 160],
                'barcode' => ['type' => ['string', 'null'], 'maxLength' => 14],
                'estimated_price_tnd' => ['type' => ['string', 'null'], 'description' => 'Prix estimé en TND avec 3 décimales, par exemple 1.650.'],
                'vat_code' => ['type' => ['string', 'null'], 'enum' => ['TVA_0', 'TVA_7', 'TVA_13', 'TVA_19', null]],
                'name_ar' => ['type' => ['string', 'null'], 'maxLength' => 255],
                'name_tn_latin' => ['type' => ['string', 'null'], 'maxLength' => 255],
                'confidence' => ['type' => 'string', 'description' => 'Score de confiance entre 0.000 et 1.000.'],
                'warnings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'brand',
                'barcode',
                'estimated_price_tnd',
                'vat_code',
                'name_ar',
                'name_tn_latin',
                'confidence',
                'warnings',
            ],
        ];
    }
}
