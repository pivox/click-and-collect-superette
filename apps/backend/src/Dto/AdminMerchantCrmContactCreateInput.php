<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\MerchantCrmContactChannel;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminMerchantCrmContactCreateInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [MerchantCrmContactChannel::class, 'values'])]
    public ?string $channel;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public ?string $note;

    #[SerializedName('contacted_at')]
    public ?string $contactedAt;

    public function __construct(
        ?string $channel = null,
        ?string $note = null,
        ?string $contactedAt = null,
    ) {
        $this->channel = $channel;
        $this->note = null !== $note ? trim($note) : null;
        $this->contactedAt = null !== $contactedAt ? trim($contactedAt) : null;
    }
}
