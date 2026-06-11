<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\MerchantCrmContact;
use App\Entity\MerchantCrmProfile;
use App\Enum\MerchantCrmStatus;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantCrmOutput
{
    /**
     * @param list<AdminMerchantCrmContactOutput> $contacts
     */
    public function __construct(
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('commercial_owner')]
        public ?string $commercialOwner,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        public string $status,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('last_contact_at')]
        public ?string $lastContactAt,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('next_action_at')]
        public ?string $nextActionAt,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('next_action_note')]
        public ?string $nextActionNote,
        #[Groups(['admin_merchant:read', 'admin_merchant_list:read'])]
        #[SerializedName('commercial_note')]
        public ?string $commercialNote,
        #[Groups(['admin_merchant:read'])]
        public array $contacts = [],
    ) {
    }

    /**
     * @param list<MerchantCrmContact> $contacts
     */
    public static function fromProfile(?MerchantCrmProfile $profile, array $contacts = []): self
    {
        return new self(
            commercialOwner: $profile?->getCommercialOwner(),
            status: ($profile?->getStatus() ?? MerchantCrmStatus::Prospect)->value,
            lastContactAt: $profile?->getLastContactAt()?->format(\DateTimeInterface::ATOM),
            nextActionAt: $profile?->getNextActionAt()?->format(\DateTimeInterface::ATOM),
            nextActionNote: $profile?->getNextActionNote(),
            commercialNote: $profile?->getCommercialNote(),
            contacts: array_map(
                static fn (MerchantCrmContact $contact): AdminMerchantCrmContactOutput => AdminMerchantCrmContactOutput::fromContact($contact),
                $contacts,
            ),
        );
    }
}
