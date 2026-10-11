<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\AdminSubscriptionPaymentCreateInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validation;

final class AdminSubscriptionPaymentCreateInputTest extends TestCase
{
    public function testIdempotencyKeyMayBeOmittedButCannotBeBlankOrInvalid(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $input = new AdminSubscriptionPaymentCreateInput();
        $input->billingDocumentId = Uuid::v4()->toRfc4122();
        $input->amountTnd = '1.000';
        $input->method = 'cash';
        $input->paidAt = '2026-01-01T10:00:00+01:00';
        foreach ([null, Uuid::v4()->toRfc4122()] as $validKey) {
            $input->idempotencyKey = $validKey;
            self::assertCount(0, $validator->validate($input));
        }
        foreach (['', ' ', 'invalid'] as $invalidKey) {
            $input->idempotencyKey = $invalidKey;
            self::assertGreaterThan(0, $validator->validate($input)->count());
        }
    }
}
