<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Dto\AdminSubscriptionPricingInput;
use App\Processor\AdminIssueSubscriptionBillingDocumentProcessor;
use App\Processor\AdminSubscriptionPricingProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/subscriptions',
            read: false,
            name: 'admin_subscription_create',
        ),
        new Post(
            uriTemplate: '/admin/subscriptions/pricing-preview',
            status: 200,
            read: false,
            name: 'admin_subscription_create_preview',
        ),
        new Put(
            uriTemplate: '/admin/subscriptions/{subscriptionId<[0-9a-fA-F\-]{32,36}>}/pricing',
            uriVariables: ['subscriptionId' => new Link(fromClass: SubscriptionOutput::class, identifiers: ['id'])],
            read: false,
            name: 'admin_subscription_pricing',
        ),
        new Post(
            uriTemplate: '/admin/subscriptions/{subscriptionId<[0-9a-fA-F\-]{32,36}>}/pricing-preview',
            uriVariables: ['subscriptionId' => new Link(fromClass: SubscriptionOutput::class, identifiers: ['id'])],
            status: 200,
            read: false,
            name: 'admin_subscription_pricing_preview',
        ),
        new Post(
            uriTemplate: '/admin/subscriptions/{subscriptionId<[0-9a-fA-F\-]{32,36}>}/billing-document',
            uriVariables: ['subscriptionId' => new Link(fromClass: SubscriptionOutput::class, identifiers: ['id'])],
            status: 200,
            normalizationContext: ['groups' => ['billing_document:read']],
            input: false,
            output: BillingDocumentOutput::class,
            read: false,
            deserialize: false,
            processor: AdminIssueSubscriptionBillingDocumentProcessor::class,
            name: 'admin_subscription_billing_document',
        ),
    ],
    formats: ['json' => ['application/json']],
    normalizationContext: ['groups' => ['subscription:read'], 'skip_null_values' => false],
    security: "is_granted('ROLE_ADMIN')",
    input: AdminSubscriptionPricingInput::class,
    output: SubscriptionOutput::class,
    processor: AdminSubscriptionPricingProcessor::class,
)]
final class AdminSubscriptionPricingResource
{
}
