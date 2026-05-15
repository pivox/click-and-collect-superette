<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\CustomerRegistrationInput;
use App\Processor\CustomerRegistrationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/register/customer',
            formats: ['json' => ['application/json']],
            input: CustomerRegistrationInput::class,
            normalizationContext: ['groups' => ['customer_registration:read']],
            status: 201,
            read: false,
            processor: CustomerRegistrationProcessor::class,
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class CustomerRegistrationOutput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['customer_registration:read'])]
        public string $id,
        #[Groups(['customer_registration:read'])]
        public string $email,
        #[Groups(['customer_registration:read'])]
        public array $roles,
        #[Groups(['customer_registration:read'])]
        #[SerializedName('first_name')]
        public string $firstName,
        #[Groups(['customer_registration:read'])]
        #[SerializedName('last_name')]
        public string $lastName,
        #[Groups(['customer_registration:read'])]
        public ?string $phone,
    ) {
    }
}
