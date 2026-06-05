<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\OpenAiMerchantCatalogPhotoImportExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class OpenAiMerchantCatalogPhotoImportExtractorTest extends TestCase
{
    public function testExtractorIgnoresNonNumericVolumeInsteadOfRaisingValueError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'products' => [[
                                'name_fr' => 'Café moulu',
                                'brand' => 'Bondin',
                                'volume' => '500 ml',
                                'unit' => 'millilitre',
                                'barcode' => null,
                                'suggested_price_tnd' => '6.900',
                                'confidence' => '0.810',
                            ]],
                        ], \JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $extractor = new OpenAiMerchantCatalogPhotoImportExtractor($httpClient, 'test-key', 'gpt-test');
        $photoPath = tempnam(sys_get_temp_dir(), 'photo-import-extractor-');
        self::assertIsString($photoPath);
        file_put_contents($photoPath, 'fake-image');

        $products = $extractor->extract(
            new UploadedFile($photoPath, 'rayon.jpg', 'image/jpeg', null, true),
            'shelf',
        );

        self::assertCount(1, $products);
        self::assertNull($products[0]->volume);
        self::assertSame('6.900', $products[0]->suggestedPriceTnd);
        self::assertSame('0.810', $products[0]->confidence);
    }
}
