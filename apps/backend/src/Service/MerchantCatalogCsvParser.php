<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ProductUnit;

final readonly class MerchantCatalogCsvParser
{
    private const array REQUIRED_COLUMNS = [
        'name_fr',
        'brand',
        'volume',
        'unit',
        'price_tnd',
        'is_available',
        'is_visible',
    ];

    public function parse(string $csv): MerchantCatalogCsvParseResult
    {
        $csv = $this->stripBom($csv);
        if ('' === trim($csv)) {
            return new MerchantCatalogCsvParseResult([], [
                new MerchantCatalogCsvParseError(1, 'CSV_EMPTY', null, 'Le fichier CSV est vide.'),
            ], 0);
        }

        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new \RuntimeException('Unable to open temporary CSV stream.');
        }

        fwrite($stream, $csv);
        rewind($stream);
        $firstLine = fgets($stream) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if (false === $header || [null] === $header) {
            fclose($stream);

            return new MerchantCatalogCsvParseResult([], [
                new MerchantCatalogCsvParseError(1, 'HEADER_REQUIRED', null, 'La ligne d’en-tête CSV est obligatoire.'),
            ], 0);
        }

        $columns = $this->normalizeHeader($header);
        $headerErrors = $this->validateRequiredColumns($columns);
        if ([] !== $headerErrors) {
            fclose($stream);

            return new MerchantCatalogCsvParseResult([], $headerErrors, 0);
        }

        $rows = [];
        $errors = [];
        $ignored = 0;
        $line = 1;

        while (false !== ($data = fgetcsv($stream, 0, $delimiter, '"', ''))) {
            ++$line;
            if ($this->isBlankCsvRow($data)) {
                ++$ignored;
                continue;
            }

            $row = $this->combineRow($columns, $data);
            $rowErrors = $this->validateRow($line, $row);
            if ([] !== $rowErrors) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $unit = $this->parseUnit($this->requiredValue($row, 'unit'));
            $isAvailable = $this->parseBoolean($this->requiredValue($row, 'is_available'));
            $isVisible = $this->parseBoolean($this->requiredValue($row, 'is_visible'));
            if (null === $unit || null === $isAvailable || null === $isVisible) {
                throw new \LogicException('Validated CSV row contains invalid normalized values.');
            }

            $rows[] = new MerchantCatalogCsvParsedRow(
                line: $line,
                nameFr: $this->requiredValue($row, 'name_fr'),
                nameAr: $this->optionalValue($row, 'name_ar'),
                brand: $this->requiredValue($row, 'brand'),
                volume: $this->normalizeDecimal($this->requiredValue($row, 'volume')),
                unit: $unit,
                priceTnd: $this->normalizeDecimal($this->requiredValue($row, 'price_tnd')),
                isAvailable: $isAvailable,
                isVisible: $isVisible,
                barcode: $this->optionalValue($row, 'barcode'),
                category: $this->optionalValue($row, 'category'),
                variantFr: $this->optionalValue($row, 'variant_fr'),
                merchantNote: $this->optionalValue($row, 'merchant_note'),
                packQuantity: $this->parsePackQuantity($this->optionalValue($row, 'pack_quantity')),
            );
        }

        fclose($stream);

        return new MerchantCatalogCsvParseResult($rows, $errors, $ignored);
    }

    /**
     * @param list<string|null> $header
     *
     * @return list<string>
     */
    private function normalizeHeader(array $header): array
    {
        return array_map(
            fn (?string $column): string => strtolower(trim($this->stripBom((string) $column))),
            $header,
        );
    }

    /**
     * @param list<string> $columns
     *
     * @return list<MerchantCatalogCsvParseError>
     */
    private function validateRequiredColumns(array $columns): array
    {
        $errors = [];
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!\in_array($column, $columns, true)) {
                $errors[] = new MerchantCatalogCsvParseError(
                    line: 1,
                    code: 'COLUMN_REQUIRED',
                    field: $column,
                    message: \sprintf('La colonne "%s" est obligatoire.', $column),
                );
            }
        }

        return $errors;
    }

    /**
     * @param list<string>      $columns
     * @param list<string|null> $data
     *
     * @return array<string, string>
     */
    private function combineRow(array $columns, array $data): array
    {
        $row = [];
        foreach ($columns as $index => $column) {
            $row[$column] = trim((string) ($data[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @param array<string, string> $row
     *
     * @return list<MerchantCatalogCsvParseError>
     */
    private function validateRow(int $line, array $row): array
    {
        $errors = [];

        foreach (['name_fr' => 'NAME_FR_REQUIRED', 'brand' => 'BRAND_REQUIRED', 'volume' => 'VOLUME_REQUIRED'] as $field => $code) {
            if ('' === $this->requiredValue($row, $field)) {
                $errors[] = new MerchantCatalogCsvParseError($line, $code, $field, \sprintf('La colonne "%s" est obligatoire.', $field));
            }
        }

        if (null === $this->parseUnit($this->requiredValue($row, 'unit'))) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'UNIT_INVALID', 'unit', 'L’unité doit être une unité produit MVP valide.');
        }

        if (!$this->isPositiveDecimal($this->requiredValue($row, 'volume'))) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'VOLUME_INVALID', 'volume', 'Le format doit contenir un volume positif.');
        }

        if (!$this->isPositiveDecimal($this->requiredValue($row, 'price_tnd'))) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'PRICE_TND_INVALID', 'price_tnd', 'Le prix TND doit être positif avec au maximum 3 décimales.');
        }

        if (null === $this->parseBoolean($this->requiredValue($row, 'is_available'))) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'IS_AVAILABLE_INVALID', 'is_available', 'La disponibilité doit être true/false, oui/non ou 1/0.');
        }

        if (null === $this->parseBoolean($this->requiredValue($row, 'is_visible'))) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'IS_VISIBLE_INVALID', 'is_visible', 'La visibilité doit être true/false, oui/non ou 1/0.');
        }

        $barcode = $this->optionalValue($row, 'barcode');
        if (null !== $barcode && 1 !== preg_match('/^[0-9]{8,14}$/', $barcode)) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'BARCODE_INVALID', 'barcode', 'Le code-barres doit contenir 8 à 14 chiffres.');
        }

        $packQuantity = $this->optionalValue($row, 'pack_quantity');
        if (null !== $packQuantity && (1 !== preg_match('/^[1-9][0-9]*$/', $packQuantity))) {
            $errors[] = new MerchantCatalogCsvParseError($line, 'PACK_QUANTITY_INVALID', 'pack_quantity', 'La quantité de pack doit être un entier positif.');
        }

        return $errors;
    }

    /**
     * @param list<string|null> $data
     */
    private function isBlankCsvRow(array $data): bool
    {
        foreach ($data as $value) {
            if ('' !== trim((string) $value)) {
                return false;
            }
        }

        return true;
    }

    private function parseUnit(string $value): ?ProductUnit
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'l', 'litre', 'litres' => ProductUnit::Litre,
            'ml', 'millilitre', 'millilitres' => ProductUnit::Millilitre,
            'kg', 'kilogramme', 'kilogrammes', 'kilo' => ProductUnit::Kilogramme,
            'g', 'gr', 'gramme', 'grammes' => ProductUnit::Gramme,
            'piece', 'pièce', 'pc', 'pcs', 'unite', 'unité' => ProductUnit::Piece,
            'paquet', 'pack' => ProductUnit::Paquet,
            default => ProductUnit::tryFrom($normalized),
        };
    }

    private function parseBoolean(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'oui', 'available', 'disponible' => true,
            '0', 'false', 'no', 'non', 'unavailable', 'indisponible' => false,
            default => null,
        };
    }

    private function parsePackQuantity(?string $value): int
    {
        return null === $value ? 1 : (int) $value;
    }

    /**
     * @param array<string, string> $row
     */
    private function requiredValue(array $row, string $field): string
    {
        return trim($row[$field] ?? '');
    }

    /**
     * @param array<string, string> $row
     */
    private function optionalValue(array $row, string $field): ?string
    {
        $value = trim($row[$field] ?? '');

        return '' === $value ? null : $value;
    }

    private function isPositiveDecimal(string $value): bool
    {
        $normalized = str_replace(',', '.', trim($value));
        if (1 !== preg_match('/^\d{1,7}(?:\.\d{1,3})?$/', $normalized)) {
            return false;
        }

        return bccomp($this->normalizeDecimal($normalized), '0.000', 3) > 0;
    }

    private function normalizeDecimal(string $value): string
    {
        return bcadd(str_replace(',', '.', trim($value)), '0', 3);
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
