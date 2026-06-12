<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\MerchantCrmContact;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminMerchantCrmContactOutput
{
    public function __construct(
        #[Groups(['admin_merchant:read'])]
        public string $id,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('contacted_at')]
        public string $contactedAt,
        #[Groups(['admin_merchant:read'])]
        public string $channel,
        #[Groups(['admin_merchant:read'])]
        public string $note,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('author_email')]
        public string $authorEmail,
        #[Groups(['admin_merchant:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }

    public static function fromContact(MerchantCrmContact $contact): self
    {
        return new self(
            id: $contact->getId()->toRfc4122(),
            contactedAt: $contact->getContactedAt()->format(\DateTimeInterface::ATOM),
            channel: $contact->getChannel()->value,
            note: $contact->getNote(),
            authorEmail: $contact->getCreatedByAdmin()->getEmail(),
            createdAt: $contact->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
