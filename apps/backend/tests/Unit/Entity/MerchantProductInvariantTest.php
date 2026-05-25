<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Entity\ProductReference;
use App\Entity\Shop;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class MerchantProductInvariantTest extends TestCase
{
    public function testValidationRejectsMerchantProductWithoutProductSource(): void
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop(new Shop())
            ->setPriceTnd('1.000');

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($merchantProduct);

        self::assertSame(1, $violations->count());
        self::assertSame('exactlyOneProductSource', $violations->get(0)->getPropertyPath());
    }

    public function testValidationRejectsMerchantProductWithBothProductSources(): void
    {
        $merchantProduct = (new MerchantProduct())
            ->setShop(new Shop())
            ->setProductReference(new ProductReference())
            ->setPriceTnd('1.000');
        $this->setPrivateProperty($merchantProduct, 'localProduct', new MerchantLocalProduct());

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($merchantProduct);

        self::assertSame(1, $violations->count());
        self::assertSame('exactlyOneProductSource', $violations->get(0)->getPropertyPath());
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
    }
}
