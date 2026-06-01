<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use App\Enum\ProductAiEnrichmentJobStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Service\ProductAiEnrichmentPayloadFactory;
use PHPUnit\Framework\TestCase;

final class ProductAiEnrichmentPayloadFactoryTest extends TestCase
{
    public function testBuildsResponsesBatchRequestWithStrictStructuredOutput(): void
    {
        $productReference = (new ProductReference())
            ->setBrand((new Brand())->setCanonicalName('Marque non vérifiée')->setSlug('marque-non-verifiee'))
            ->setCategory((new Category())->setNameFr('Boissons')->setSlug('boissons'))
            ->setNameFr('Eau minerale 1.5 l')
            ->setVolume('1.500')
            ->setUnit(ProductUnit::Litre)
            ->setStatus(ProductReferenceStatus::Approved)
            ->setAliases(['SUPTUN-0001', 'eaux']);

        $job = new ProductAiEnrichmentJob($productReference);
        $factory = new ProductAiEnrichmentPayloadFactory();

        $request = $factory->buildBatchRequest($job, 'gpt-4o-mini');

        self::assertSame($job->getId()->toRfc4122(), $request['custom_id']);
        self::assertSame('POST', $request['method']);
        self::assertSame('/v1/responses', $request['url']);
        self::assertSame('gpt-4o-mini', $request['body']['model']);
        self::assertSame('json_schema', $request['body']['text']['format']['type']);
        self::assertTrue($request['body']['text']['format']['strict']);
        self::assertSame('product_ai_enrichment', $request['body']['text']['format']['name']);
        self::assertArrayHasKey('brand', $request['body']['text']['format']['schema']['properties']);
        self::assertArrayHasKey('barcode', $request['body']['text']['format']['schema']['properties']);
        self::assertArrayHasKey('estimated_price_tnd', $request['body']['text']['format']['schema']['properties']);
        self::assertArrayHasKey('vat_code', $request['body']['text']['format']['schema']['properties']);
        self::assertStringContainsString('Eau minerale 1.5 l', json_encode($request['body']['input'], \JSON_THROW_ON_ERROR));
        self::assertSame(ProductAiEnrichmentJobStatus::Pending, $job->getStatus());
    }
}
