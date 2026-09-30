<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\WhatsappContactLinkFactory;
use PHPUnit\Framework\TestCase;

final class WhatsappContactLinkFactoryTest extends TestCase
{
    private WhatsappContactLinkFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WhatsappContactLinkFactory();
    }

    public function testNormalizePhoneReturnsNullForNull(): void
    {
        self::assertNull($this->factory->normalizePhone(null));
    }

    public function testNormalizePhoneReturnsNullForEmptyOrBlankString(): void
    {
        self::assertNull($this->factory->normalizePhone(''));
        self::assertNull($this->factory->normalizePhone('   '));
    }

    public function testNormalizePhoneReturnsNullWhenNoDigits(): void
    {
        self::assertNull($this->factory->normalizePhone('abc'));
        self::assertNull($this->factory->normalizePhone('+-()'));
    }

    public function testNormalizePhonePrefixesEightDigitLocalNumberWithCountryCode(): void
    {
        self::assertSame('21620123456', $this->factory->normalizePhone('20123456'));
        self::assertSame('21620123456', $this->factory->normalizePhone('20 123 456'));
    }

    public function testNormalizePhoneStripsDoubleZeroInternationalPrefix(): void
    {
        self::assertSame('21620123456', $this->factory->normalizePhone('0021620123456'));
    }

    public function testNormalizePhoneStripsNonDigitCharacters(): void
    {
        self::assertSame('21620123456', $this->factory->normalizePhone('+216 20-123-456'));
    }

    public function testNormalizePhoneKeepsAlreadyPrefixedNumberUnchanged(): void
    {
        self::assertSame('21698765432', $this->factory->normalizePhone('21698765432'));
    }

    public function testBuildUrlEncodesMessageForWaMe(): void
    {
        $url = $this->factory->buildUrl('21620123456', 'Bonjour, commande #0042 chez Supérette El Amen.');

        self::assertSame(
            'https://wa.me/21620123456?text=Bonjour%2C%20commande%20%230042%20chez%20Sup%C3%A9rette%20El%20Amen.',
            $url,
        );
    }
}
