<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\CustomerRegistrationInput;
use App\Processor\CustomerRegistrationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

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
        ),
    ],
)]
final readonly class CustomerRegistrationOutput
{
    /**
     * @param array<string, mixed> $user
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['customer_registration:read'])]
        public string $token,
        #[Groups(['customer_registration:read'])]
        public array $user,
    ) {
    }
}
