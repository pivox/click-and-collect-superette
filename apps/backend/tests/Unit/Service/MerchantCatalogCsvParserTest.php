<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MerchantCatalogCsvParser;
use PHPUnit\Framework\TestCase;

final class MerchantCatalogCsvParserTest extends TestCase
{
    public function testParsesSemicolonCsvAndNormalizesBusinessFields(): void
    {
        $parser = new MerchantCatalogCsvParser();
        $csv = "name_fr;brand;volume;unit;price_tnd;is_available;is_visible;barcode;category;merchant_note\n"
            ."Lait demi-écrémé;Vitalait;1;litre;1.650;oui;non;6191234567890;Lait & produits laitiers;Promo matin\n";

        $result = $parser->parse($csv);

        self::assertCount(1, $result->rows);
        self::assertSame([], $result->errors);
        self::assertSame(2, $result->rows[0]->line);
        self::assertSame('Lait demi-écrémé', $result->rows[0]->nameFr);
        self::assertSame('Vitalait', $result->rows[0]->brand);
        self::assertSame('1.000', $result->rows[0]->volume);
        self::assertSame('litre', $result->rows[0]->unit->value);
        self::assertSame('1.650', $result->rows[0]->priceTnd);
        self::assertTrue($result->rows[0]->isAvailable);
        self::assertFalse($result->rows[0]->isVisible);
        self::assertSame('6191234567890', $result->rows[0]->barcode);
        self::assertSame('Lait & produits laitiers', $result->rows[0]->category);
        self::assertSame('Promo matin', $result->rows[0]->merchantNote);
    }

    public function testReportsLineValidationErrorsWithoutDroppingOtherRows(): void
    {
        $parser = new MerchantCatalogCsvParser();
        $csv = "name_fr,brand,volume,unit,price_tnd,is_available,is_visible,barcode\n"
            ."Lait entier,Vitalait,1,litre,1.700,true,true,6191234567890\n"
            .",Vitalait,1,litre,1.700,true,true,619123456789012345\n"
            ."Boga Boga,Boga,1,litre,-2,true,maybe,\n";

        $result = $parser->parse($csv);

        self::assertCount(1, $result->rows);
        self::assertCount(4, $result->errors);
        self::assertSame(3, $result->errors[0]->line);
        self::assertSame('NAME_FR_REQUIRED', $result->errors[0]->code);
        self::assertSame('BARCODE_INVALID', $result->errors[1]->code);
        self::assertSame(4, $result->errors[2]->line);
        self::assertSame('PRICE_TND_INVALID', $result->errors[2]->code);
        self::assertSame('IS_VISIBLE_INVALID', $result->errors[3]->code);
    }

    public function testRequiresCsvHeaderColumns(): void
    {
        $parser = new MerchantCatalogCsvParser();

        $result = $parser->parse("name_fr,brand,price_tnd\nLait,Vitalait,1.500\n");

        self::assertSame([], $result->rows);
        self::assertCount(4, $result->errors);
        self::assertSame(1, $result->errors[0]->line);
        self::assertSame('COLUMN_REQUIRED', $result->errors[0]->code);
        self::assertSame('volume', $result->errors[0]->field);
        self::assertSame('unit', $result->errors[1]->field);
        self::assertSame('is_available', $result->errors[2]->field);
        self::assertSame('is_visible', $result->errors[3]->field);
    }
}
