<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Dto\CustomerProfilePatchInput;
use App\Processor\CustomerProfileProcessor;
use App\Provider\CustomerProfileProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/profile',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['customer_profile:read']],
            provider: CustomerProfileProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
        new Patch(
            uriTemplate: '/me/profile',
            formats: ['json' => ['application/json']],
            input: CustomerProfilePatchInput::class,
            normalizationContext: ['groups' => ['customer_profile:read']],
            read: false,
            processor: CustomerProfileProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerProfileOutput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Groups(['customer_profile:read'])]
        public string $id,
        #[Groups(['customer_profile:read'])]
        public string $email,
        #[Groups(['customer_profile:read'])]
        public array $roles,
        #[Groups(['customer_profile:read'])]
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[Groups(['customer_profile:read'])]
        #[SerializedName('last_name')]
        public ?string $lastName,
        #[Groups(['customer_profile:read'])]
        public ?string $phone,
    ) {
    }
}
