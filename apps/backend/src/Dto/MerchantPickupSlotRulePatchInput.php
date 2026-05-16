<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MerchantPickupSlotRulePatchInput
{
    #[Assert\Range(min: 1, max: 7)]
    public ?int $weekday = null;

    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'PICKUP_SLOT_RULE_INVALID_START_TIME')]
    #[SerializedName('start_time')]
    public ?string $startTime = null;

    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'PICKUP_SLOT_RULE_INVALID_END_TIME')]
    #[SerializedName('end_time')]
    public ?string $endTime = null;

    #[Assert\Positive]
    public ?int $capacity = null;

    #[Assert\Callback]
    public function validateTimeRangeWhenComplete(ExecutionContextInterface $context): void
    {
        if (null === $this->startTime || null === $this->endTime) {
            return;
        }

        if ($this->startTime >= $this->endTime) {
            $context->buildViolation('PICKUP_SLOT_RULE_START_TIME_MUST_BE_BEFORE_END_TIME')
                ->atPath('startTime')
                ->addViolation();
        }
    }
}
