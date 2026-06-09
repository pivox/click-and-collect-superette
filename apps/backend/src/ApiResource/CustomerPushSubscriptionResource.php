<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Dto\PushSubscriptionInput;
use App\Processor\RegisterCustomerPushSubscriptionProcessor;
use App\Processor\UnregisterCustomerPushSubscriptionProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/me/push-subscriptions',
            formats: ['json' => ['application/json']],
            input: PushSubscriptionInput::class,
            output: false,
            status: 201,
            processor: RegisterCustomerPushSubscriptionProcessor::class,
            validate: true,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Delete(
            uriTemplate: '/me/push-subscriptions',
            formats: ['json' => ['application/json']],
            input: PushSubscriptionInput::class,
            output: false,
            status: 204,
            processor: UnregisterCustomerPushSubscriptionProcessor::class,
            validate: true,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerPushSubscriptionResource
{
}
