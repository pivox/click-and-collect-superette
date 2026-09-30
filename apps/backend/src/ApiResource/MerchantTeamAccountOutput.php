<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\MerchantMembership;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * One account of the merchant organization team (MERCHANT-TEAM-004).
 * Never exposes password hashes, stored tokens or mutable roles.
 */
final readonly class MerchantTeamAccountOutput
{
    public function __construct(
        #[Groups(['merchant_team:read'])]
        #[SerializedName('account_id')]
        public string $accountId,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('first_name')]
        public ?string $firstName,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('last_name')]
        public ?string $lastName,
        #[Groups(['merchant_team:read'])]
        public string $email,
        #[Groups(['merchant_team:read'])]
        public ?string $phone,
        #[Groups(['merchant_team:read'])]
        public string $status,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('is_primary')]
        public bool $isPrimary,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('invited_at')]
        public ?string $invitedAt,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('accepted_at')]
        public ?string $acceptedAt,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('revoked_at')]
        public ?string $revokedAt,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('invitation_status')]
        public ?string $invitationStatus = null,
    ) {
    }

    public static function fromMembership(MerchantMembership $membership, ?string $invitationStatus = null): self
    {
        $user = $membership->getUser();
        $organization = $membership->getOrganization();

        return new self(
            accountId: (string) $user?->getId()->toRfc4122(),
            firstName: $user?->getFirstName(),
            lastName: $user?->getLastName(),
            email: (string) $user?->getEmail(),
            phone: $user?->getPhone(),
            status: $membership->getStatus()->value,
            isPrimary: true === $organization?->getPrimaryAccount()?->getId()->equals($user?->getId()),
            invitedAt: $membership->getInvitedAt()?->format(\DateTimeInterface::ATOM),
            acceptedAt: $membership->getAcceptedAt()?->format(\DateTimeInterface::ATOM),
            revokedAt: $membership->getRevokedAt()?->format(\DateTimeInterface::ATOM),
            invitationStatus: $invitationStatus,
        );
    }
}
