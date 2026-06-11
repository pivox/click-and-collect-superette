<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\MerchantCrmStatus;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminMerchantCrmUpdateInput
{
    #[Assert\Length(max: 120)]
    #[SerializedName('commercial_owner')]
    public ?string $commercialOwner;

    #[Assert\Choice(callback: [MerchantCrmStatus::class, 'values'])]
    public ?string $status;

    #[SerializedName('next_action_at')]
    public ?string $nextActionAt;

    #[Assert\Length(max: 255)]
    #[SerializedName('next_action_note')]
    public ?string $nextActionNote;

    #[Assert\Length(max: 5000)]
    #[SerializedName('commercial_note')]
    public ?string $commercialNote;

    public function __construct(
        ?string $commercialOwner = null,
        ?string $status = null,
        ?string $nextActionAt = null,
        ?string $nextActionNote = null,
        ?string $commercialNote = null,
    ) {
        $this->commercialOwner = null !== $commercialOwner ? trim($commercialOwner) : null;
        $this->status = $status;
        $this->nextActionAt = null !== $nextActionAt ? trim($nextActionAt) : null;
        $this->nextActionNote = null !== $nextActionNote ? trim($nextActionNote) : null;
        $this->commercialNote = null !== $commercialNote ? trim($commercialNote) : null;
    }
}
