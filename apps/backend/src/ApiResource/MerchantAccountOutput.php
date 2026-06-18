<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use App\Dto\MerchantMeUpdateInput;
use App\Dto\MerchantPasswordChangeInput;
use App\Entity\User;
use App\Processor\ChangeMerchantPasswordProcessor;
use App\Processor\UpdateMerchantAccountProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Current merchant account, editable by its owner (MS-003).
 *
 * Decoupled from the store: GET /merchant/me (with store) stays on
 * MerchantMeOutput; these PATCH operations only touch the User account, so
 * they never depend on shop resolution.
 */
#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/merchant/me',
            formats: ['json' => ['application/json']],
            input: MerchantMeUpdateInput::class,
            output: self::class,
            read: false,
            processor: UpdateMerchantAccountProcessor::class,
            normalizationContext: ['groups' => ['merchant_account:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/merchant/me/password',
            formats: ['json' => ['application/json']],
            status: 204,
            input: MerchantPasswordChangeInput::class,
            output: false,
            read: false,
            processor: ChangeMerchantPasswordProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantAccountOutput
{
    public function __construct(
        #[Groups(['merchant_account:read'])]
        #[SerializedName('user_id')]
        public string $userId,
        #[Groups(['merchant_account:read'])]
        public string $email,
        #[Groups(['merchant_account:read'])]
        public string $name,
        #[Groups(['merchant_account:read'])]
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[Groups(['merchant_account:read'])]
        #[SerializedName('last_name')]
        public ?string $lastName,
        #[Groups(['merchant_account:read'])]
        public ?string $phone,
    ) {
    }

    public static function fromUser(User $merchant): self
    {
        return new self(
            userId: $merchant->getId()->toRfc4122(),
            email: $merchant->getEmail(),
            name: $merchant->getName(),
            firstName: $merchant->getFirstName(),
            lastName: $merchant->getLastName(),
            phone: $merchant->getPhone(),
        );
    }
}
