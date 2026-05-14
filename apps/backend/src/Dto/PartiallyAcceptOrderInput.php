<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PartiallyAcceptOrderInput
{
    /** @var list<string> */
    #[Assert\Count(min: 1, minMessage: 'NO_LINES_REJECTED')]
    #[Assert\All([new Assert\Uuid()])]
    #[SerializedName('rejected_merchant_product_ids')]
    public array $rejectedMerchantProductIds;

    #[Assert\Length(max: 500)]
    public ?string $notes;

    /**
     * @param list<string> $rejectedMerchantProductIds
     */
    public function __construct(
        array $rejectedMerchantProductIds = [],
        ?string $notes = null,
    ) {
        $notes = null !== $notes ? trim($notes) : null;
        $this->rejectedMerchantProductIds = $rejectedMerchantProductIds;
        $this->notes = null === $notes || '' === $notes ? null : $notes;
    }
}
