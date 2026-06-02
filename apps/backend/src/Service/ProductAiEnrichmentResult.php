<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ProductAiEnrichmentResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public ?string $brand,
        public ?string $barcode,
        public ?string $estimatedPriceTnd,
        public ?string $vatCode,
        public ?string $nameAr,
        public ?string $nameTnLatin,
        public string $confidence,
        public array $warnings = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            brand: self::nullableString($payload['brand'] ?? null),
            barcode: self::nullableString($payload['barcode'] ?? null),
            estimatedPriceTnd: self::nullableString($payload['estimated_price_tnd'] ?? null),
            vatCode: self::nullableString($payload['vat_code'] ?? null),
            nameAr: self::nullableString($payload['name_ar'] ?? null),
            nameTnLatin: self::nullableString($payload['name_tn_latin'] ?? null),
            confidence: self::nullableString($payload['confidence'] ?? null) ?? '0.000',
            warnings: self::stringList($payload['warnings'] ?? []),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return '' === $string ? null : $string;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $string = self::nullableString($item);
            if (null !== $string) {
                $items[] = $string;
            }
        }

        return $items;
    }
}
