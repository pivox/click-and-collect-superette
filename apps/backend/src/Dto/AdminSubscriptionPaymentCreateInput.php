<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminSubscriptionPaymentCreateInput
{
    #[SerializedName('billing_document_id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $billingDocumentId = null;

    #[SerializedName('amount_tnd')]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,3})?$/')]
    public ?string $amountTnd = null;

    #[Assert\NotBlank]
    #[Assert\Choice(['cash', 'bank_transfer'])]
    public ?string $method = null;

    #[SerializedName('paid_at')]
    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public ?string $paidAt = null;

    #[Assert\Length(max: 120)]
    public ?string $reference = null;
}
