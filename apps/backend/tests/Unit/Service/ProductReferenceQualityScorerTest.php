<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Service\ProductReferenceQualityScorer;
use PHPUnit\Framework\TestCase;

final class ProductReferenceQualityScorerTest extends TestCase
{
    private ProductReferenceQualityScorer $scorer;
    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        $this->scorer = new ProductReferenceQualityScorer();
        $this->brand = (new Brand())
            ->setCanonicalName('Vitalait')
            ->setSlug('vitalait');
        $this->category = (new Category())
            ->setNameFr('Laits')
            ->setSlug('laits');
    }

    public function testCompleteApprovedReferenceWithImageScoresOneHundred(): void
    {
        $reference = $this->reference()
            ->setVolume('1.000')
            ->setUnit(ProductUnit::Litre)
            ->setBarcode('6191234567890')
            ->setStatus(ProductReferenceStatus::Approved);

        self::assertSame(100, $this->scorer->score($reference, hasOfficialImage: true));
    }

    public function testMissingBarcodeImageFormatAndApprovalLowersScore(): void
    {
        $reference = $this->reference();

        self::assertSame(50, $this->scorer->score($reference, hasOfficialImage: false));
    }

    public function testCandidateImageDoesNotCountAsOfficialImage(): void
    {
        $reference = $this->reference()
            ->setVolume('1.000')
            ->setBarcode('6191234567890')
            ->setStatus(ProductReferenceStatus::Approved);

        self::assertSame(85, $this->scorer->score($reference, hasOfficialImage: false));
    }

    private function reference(): ProductReference
    {
        return (new ProductReference())
            ->setBrand($this->brand)
            ->setCategory($this->category)
            ->setNameFr('Lait demi-écrémé')
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Draft);
    }
}
