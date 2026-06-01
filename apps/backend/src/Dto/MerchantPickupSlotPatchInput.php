<?php

declare(strict_types=1);

namespace App\Dto;

use App\Service\PickupSlotDuration;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MerchantPickupSlotPatchInput
{
    #[SerializedName('starts_at')]
    public ?\DateTimeImmutable $startsAt = null;

    #[SerializedName('ends_at')]
    public ?\DateTimeImmutable $endsAt = null;

    #[Assert\Positive]
    public ?int $capacity = null;

    #[SerializedName('is_active')]
    public ?bool $isActive = null;

    #[Assert\Callback]
    public function validateDateRangeWhenComplete(ExecutionContextInterface $context): void
    {
        if (null === $this->startsAt || null === $this->endsAt) {
            return;
        }

        if ($this->startsAt >= $this->endsAt) {
            $context->buildViolation('PICKUP_SLOT_STARTS_AT_MUST_BE_BEFORE_ENDS_AT')
                ->atPath('startsAt')
                ->addViolation();

            return;
        }

        if (!PickupSlotDuration::isExactlyOneHour($this->startsAt, $this->endsAt)) {
            $context->buildViolation('PICKUP_SLOT_MUST_LAST_ONE_HOUR')
                ->atPath('endsAt')
                ->addViolation();
        }
    }
}
