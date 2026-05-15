<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Dto\CustomerProfilePatchInput;
use App\Entity\User;
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
        // Singleton /me resource: JSON-only output, no User IRI is exposed.
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
        public string $name,
        #[Groups(['customer_profile:read'])]
        public ?string $phone,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->getId()->toRfc4122(),
            $user->getEmail(),
            $user->getRoles(),
            $user->getFirstName(),
            $user->getLastName(),
            $user->getName(),
            $user->getPhone(),
        );
    }
}
