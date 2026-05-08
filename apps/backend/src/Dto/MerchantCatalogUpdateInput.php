<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class MerchantCatalogUpdateInput
{
    #[Assert\Regex('/^\d+(?:\.\d{1,3})?$/')]
    #[Assert\PositiveOrZero]
    #[SerializedName('price_tnd')]
    public ?string $priceTnd = null;

    #[SerializedName('is_available')]
    public ?bool $isAvailable = null;

    #[SerializedName('is_visible')]
    public ?bool $isVisible = null;

    #[Assert\Length(max: 500)]
    private ?string $merchantNote = null;

    private bool $merchantNoteProvided = false;

    public function getMerchantNote(): ?string
    {
        return $this->merchantNote;
    }

    #[SerializedName('merchant_note')]
    public function setMerchantNote(?string $merchantNote): void
    {
        $this->merchantNote = $merchantNote;
        $this->merchantNoteProvided = true;
    }

    public function hasMerchantNote(): bool
    {
        return $this->merchantNoteProvided;
    }
}
