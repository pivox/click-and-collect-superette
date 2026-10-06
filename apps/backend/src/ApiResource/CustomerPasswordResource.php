<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use App\Dto\CustomerPasswordChangeInput;
use App\Processor\ChangeCustomerPasswordProcessor;

#[ApiResource(operations: [
    new Patch(
        uriTemplate: '/me/password',
        formats: ['json' => ['application/json']],
        status: 204,
        input: CustomerPasswordChangeInput::class,
        output: false,
        read: false,
        processor: ChangeCustomerPasswordProcessor::class,
        security: "is_granted('ROLE_CUSTOMER')",
    ),
])]
final class CustomerPasswordResource
{
}
