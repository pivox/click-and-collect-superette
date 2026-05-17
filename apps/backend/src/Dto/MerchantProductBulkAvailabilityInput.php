<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class MerchantProductBulkAvailabilityInput
{
    /**
     * @var list<string>
     */
    #[Assert\Count(min: 1, max: 50)]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Uuid(),
    ])]
    #[SerializedName('merchant_product_ids')]
    public array $merchantProductIds = [];

    #[Assert\NotNull]
    #[Assert\Type('bool')]
    #[SerializedName('is_available')]
    public mixed $isAvailable = null;

    #[Assert\Length(max: 255)]
    #[SerializedName('merchant_note')]
    public ?string $merchantNote = null;
}
