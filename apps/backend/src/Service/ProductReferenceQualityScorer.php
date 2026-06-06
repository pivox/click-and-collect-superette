<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;

final readonly class ProductReferenceQualityScorer
{
    public function score(ProductReference $productReference, bool $hasOfficialImage): int
    {
        $score = 0;

        if ('' !== trim($productReference->getNameFr())) {
            $score += 20;
        }
        if ('' !== trim($productReference->getBrand()->getCanonicalName())) {
            $score += 10;
        }
        if ('' !== trim($productReference->getCategory()->getNameFr())) {
            $score += 10;
        }
        if (null !== $productReference->getVolume()) {
            $score += 10;
        }
        $score += 10;
        if (null !== $productReference->getBarcode() && '' !== trim($productReference->getBarcode())) {
            $score += 15;
        }
        if ($hasOfficialImage) {
            $score += 15;
        }
        if (ProductReferenceStatus::Approved === $productReference->getStatus()) {
            $score += 10;
        }

        return min(100, $score);
    }

    public function level(int $score): string
    {
        if ($score >= 80) {
            return 'good';
        }

        if ($score >= 60) {
            return 'medium';
        }

        return 'low';
    }
}
