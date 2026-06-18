<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\MerchantInvitationToken;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantInvitationOutput
{
    public function __construct(
        #[Groups(['admin_merchant_invitation:read'])]
        #[SerializedName('merchant_id')]
        public string $merchantId,
        #[Groups(['admin_merchant_invitation:read'])]
        public string $status,
        #[Groups(['admin_merchant_invitation:read'])]
        #[SerializedName('expires_at')]
        public string $expiresAt,
    ) {
    }

    public static function sent(MerchantInvitationToken $token): self
    {
        return new self(
            merchantId: $token->getMerchant()->getId()->toRfc4122(),
            status: 'sent',
            expiresAt: $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
