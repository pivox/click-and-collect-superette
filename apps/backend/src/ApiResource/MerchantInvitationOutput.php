<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantInvitationCompleteInput;
use App\Dto\MerchantInvitationTokenInput;
use App\Entity\MerchantInvitationToken;
use App\Processor\CompleteMerchantInvitationProcessor;
use App\Processor\VerifyMerchantInvitationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/merchant-invitations/verify',
            formats: ['json' => ['application/json']],
            status: 200,
            input: MerchantInvitationTokenInput::class,
            output: self::class,
            read: false,
            processor: VerifyMerchantInvitationProcessor::class,
            normalizationContext: ['groups' => ['merchant_invitation:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            validate: true,
        ),
        new Post(
            uriTemplate: '/auth/merchant-invitations/complete',
            formats: ['json' => ['application/json']],
            status: 204,
            input: MerchantInvitationCompleteInput::class,
            output: false,
            read: false,
            processor: CompleteMerchantInvitationProcessor::class,
            security: "is_granted('PUBLIC_ACCESS')",
            validate: true,
        ),
    ],
)]
final readonly class MerchantInvitationOutput
{
    public function __construct(
        #[Groups(['merchant_invitation:read'])]
        public string $status,
        #[Groups(['merchant_invitation:read'])]
        #[SerializedName('expires_at')]
        public string $expiresAt,
    ) {
    }

    public static function valid(MerchantInvitationToken $token): self
    {
        return new self(
            status: 'valid',
            expiresAt: $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
