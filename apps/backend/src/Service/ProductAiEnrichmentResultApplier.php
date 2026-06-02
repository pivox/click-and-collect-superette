<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Brand;
use App\Entity\ProductAiEnrichmentJob;
use App\Entity\ProductReference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ProductAiEnrichmentResultApplier
{
    private const ALLOWED_VAT_CODES = ['TVA_0', 'TVA_7', 'TVA_13', 'TVA_19'];

    private AsciiSlugger $slugger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->slugger = new AsciiSlugger('fr');
    }

    public function apply(ProductAiEnrichmentJob $job, ProductAiEnrichmentResult $result): void
    {
        $this->validate($result);

        $productReference = $job->getProductReference();

        // Validate all preconditions before any mutation so a duplicate barcode cannot
        // leave a partial state (e.g. a newly created Brand) in the Unit of Work.
        if (null !== $result->barcode) {
            $this->assertBarcodeNotUsedByAnotherProduct($productReference, $result->barcode);
        }

        $previousValues = [
            'brand' => $productReference->getBrand()->getCanonicalName(),
            'barcode' => $productReference->getBarcode(),
            'name_ar' => $productReference->getNameAr(),
            'aliases' => $productReference->getAliases(),
            'ai_previous_values' => $productReference->getAiPreviousValues(),
        ];

        if (null !== $result->brand) {
            $productReference->setBrand($this->resolveBrand($result->brand));
        }

        if (null !== $result->barcode) {
            $productReference->setBarcode($result->barcode);
        }

        if (null !== $result->nameAr) {
            $productReference->setNameAr($result->nameAr);
        }

        if (null !== $result->nameTnLatin) {
            $aliases = $productReference->getAliases();
            $aliases[] = $result->nameTnLatin;
            $productReference->setAliases(array_values(array_unique($aliases)));
        }

        $productReference
            ->setAiEnrichedAt(new \DateTimeImmutable())
            ->setAiConfidence($result->confidence)
            ->setAiSource('openai')
            ->setAiPreviousValues($previousValues);

        $job->markSucceeded([
            'brand' => $result->brand,
            'barcode' => $result->barcode,
            'estimated_price_tnd' => $result->estimatedPriceTnd,
            'vat_code' => $result->vatCode,
            'name_ar' => $result->nameAr,
            'name_tn_latin' => $result->nameTnLatin,
            'confidence' => $result->confidence,
            'warnings' => $result->warnings,
        ]);
        $job->markApplied();
    }

    private function validate(ProductAiEnrichmentResult $result): void
    {
        if (null !== $result->barcode && 1 !== preg_match('/^[0-9]{8,14}$/', $result->barcode)) {
            throw new \InvalidArgumentException('AI_RESULT_BARCODE_INVALID');
        }

        if (null !== $result->estimatedPriceTnd && (!is_numeric($result->estimatedPriceTnd) || (float) $result->estimatedPriceTnd <= 0)) {
            throw new \InvalidArgumentException('AI_RESULT_PRICE_INVALID');
        }

        if (null !== $result->vatCode && !\in_array($result->vatCode, self::ALLOWED_VAT_CODES, true)) {
            throw new \InvalidArgumentException('AI_RESULT_VAT_INVALID');
        }

        if (!is_numeric($result->confidence) || (float) $result->confidence < 0 || (float) $result->confidence > 1) {
            throw new \InvalidArgumentException('AI_RESULT_CONFIDENCE_INVALID');
        }
    }

    private function resolveBrand(string $brandName): Brand
    {
        $slug = mb_substr(strtolower((string) $this->slugger->slug($brandName)), 0, 180);
        $existing = $this->entityManager->getRepository(Brand::class)->findOneBy(['slug' => $slug]);

        if ($existing instanceof Brand) {
            return $existing;
        }

        $brand = (new Brand())
            ->setCanonicalName($brandName)
            ->setSlug($slug)
            ->setCountry('TN');

        $this->entityManager->persist($brand);

        return $brand;
    }

    private function assertBarcodeNotUsedByAnotherProduct(ProductReference $productReference, string $barcode): void
    {
        $existing = $this->entityManager->getRepository(ProductReference::class)->findOneBy(['barcode' => $barcode]);
        if ($existing instanceof ProductReference && !$existing->getId()->equals($productReference->getId())) {
            throw new \InvalidArgumentException('AI_RESULT_BARCODE_ALREADY_USED');
        }
    }
}
