<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MerchantPickupSlotCreateInput
{
    #[Assert\NotNull]
    #[SerializedName('starts_at')]
    public ?\DateTimeImmutable $startsAt = null;

    #[Assert\NotNull]
    #[SerializedName('ends_at')]
    public ?\DateTimeImmutable $endsAt = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $capacity = null;

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if (null === $this->startsAt || null === $this->endsAt) {
            return;
        }

        if ($this->startsAt >= $this->endsAt) {
            $context->buildViolation('PICKUP_SLOT_STARTS_AT_MUST_BE_BEFORE_ENDS_AT')
                ->atPath('startsAt')
                ->addViolation();
        }
    }
}
