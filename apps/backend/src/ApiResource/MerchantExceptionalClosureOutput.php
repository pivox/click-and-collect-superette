<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantExceptionalClosureCreateInput;
use App\Dto\MerchantExceptionalClosurePatchInput;
use App\Entity\Shop;
use App\Processor\CreateMerchantExceptionalClosureProcessor;
use App\Processor\DeleteMerchantExceptionalClosureProcessor;
use App\Processor\UpdateMerchantExceptionalClosureProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/exceptional-closures',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantExceptionalClosureCreateInput::class,
            status: 201,
            read: false,
            processor: CreateMerchantExceptionalClosureProcessor::class,
            normalizationContext: ['groups' => ['merchant_exceptional_closure:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/stores/{storeId}/exceptional-closures/{closureId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'closureId' => new Link(fromClass: MerchantExceptionalClosureOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantExceptionalClosurePatchInput::class,
            read: false,
            processor: UpdateMerchantExceptionalClosureProcessor::class,
            normalizationContext: ['groups' => ['merchant_exceptional_closure:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/merchant/stores/{storeId}/exceptional-closures/{closureId}',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'closureId' => new Link(fromClass: MerchantExceptionalClosureOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: DeleteMerchantExceptionalClosureProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantExceptionalClosureOutput
{
    public function __construct(
        #[Groups(['merchant_exceptional_closure:read'])]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['merchant_exceptional_closure:read'])]
        #[SerializedName('starts_at')]
        public string $startsAt,
        #[Groups(['merchant_exceptional_closure:read'])]
        #[SerializedName('ends_at')]
        public string $endsAt,
        #[Groups(['merchant_exceptional_closure:read'])]
        public ?string $reason,
        #[Groups(['merchant_exceptional_closure:read'])]
        #[SerializedName('is_active')]
        public bool $isActive,
    ) {
    }
}
