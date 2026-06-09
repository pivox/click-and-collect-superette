<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\PushSubscriptionInput;
use App\Processor\RegisterMerchantPushSubscriptionProcessor;
use App\Processor\UnregisterMerchantPushSubscriptionProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/push-subscriptions',
            formats: ['json' => ['application/json']],
            input: PushSubscriptionInput::class,
            output: false,
            status: 201,
            processor: RegisterMerchantPushSubscriptionProcessor::class,
            validate: true,
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/push-subscriptions/unregister',
            formats: ['json' => ['application/json']],
            input: PushSubscriptionInput::class,
            output: false,
            status: 204,
            processor: UnregisterMerchantPushSubscriptionProcessor::class,
            validate: true,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantPushSubscriptionResource
{
}
