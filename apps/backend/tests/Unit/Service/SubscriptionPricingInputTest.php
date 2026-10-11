<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\AdminSubscriptionPricingInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class SubscriptionPricingInputTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidTerms')]
    public function testInvalidCommercialTermsAreRejected(array $overrides): void
    {
        $input = new AdminSubscriptionPricingInput();
        $input->billingInterval = 'monthly';
        $input->pricingMode = 'paid';
        $input->basePriceTnd = '50.000';
        $input->reason = 'Accord commercial';
        foreach ($overrides as $property => $value) {
            $input->{$property} = $value;
        }
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        self::assertGreaterThan(0, $validator->validate($input)->count());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTerms(): iterable
    {
        yield 'negative price' => [['basePriceTnd' => '-1']];
        yield 'submillime' => [['basePriceTnd' => '10.0001']];
        yield 'too large' => [['basePriceTnd' => '10000000']];
        yield 'blank reason' => [['reason' => '   ']];
        yield 'invalid interval' => [['billingInterval' => 'weekly']];
        yield 'semi paid unsupported' => [['pricingMode' => 'semi_paid']];
        yield 'duration on paid' => [['freeMonths' => 2]];
        yield 'free duration zero' => [['pricingMode' => 'free', 'freeMonths' => 0]];
        yield 'free duration too long' => [['pricingMode' => 'free', 'freeMonths' => 121]];
        yield 'missing discount' => [['pricingMode' => 'discounted']];
        foreach ([['from_month' => 0], ['to_month' => 121], ['to_month' => 0], ['value' => '101'], ['value' => '-10'], ['value' => '0'], ['type' => 'unknown'], ['from_month' => '1'], ['value' => 5]] as $change) {
            yield 'invalid rule '.json_encode($change) => [['pricingMode' => 'discounted', 'discounts' => [array_replace(['from_month' => 1, 'to_month' => 3, 'type' => 'percentage', 'value' => '50'], $change)]]];
        }
        yield 'object instead of list' => [['pricingMode' => 'discounted', 'discounts' => ['rule' => ['from_month' => 1, 'to_month' => 3, 'type' => 'percentage', 'value' => '50']]]];
        yield 'scalar rule' => [['pricingMode' => 'discounted', 'discounts' => [5]]];
    }
}
