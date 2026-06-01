<?php

declare(strict_types=1);

namespace App\Dto;

use App\Service\PickupSlotDuration;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MerchantPickupSlotRulePatchInput
{
    #[Assert\Range(min: 1, max: 7)]
    public ?int $weekday = null;

    #[Assert\Regex(pattern: '/^(?:[01]\d|2[0-3]):[0-5]\d$/', message: 'PICKUP_SLOT_RULE_INVALID_START_TIME')]
    #[SerializedName('start_time')]
    public ?string $startTime = null;

    #[Assert\Regex(pattern: '/^(?:[01]\d|2[0-3]):[0-5]\d$/', message: 'PICKUP_SLOT_RULE_INVALID_END_TIME')]
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

            return;
        }

        $startTime = self::parseTime($this->startTime);
        $endTime = self::parseTime($this->endTime);
        if (null === $startTime || null === $endTime) {
            return;
        }

        if (!PickupSlotDuration::isAtLeastOneHour($startTime, $endTime)) {
            $context->buildViolation('PICKUP_SLOT_RULE_RANGE_MUST_BE_AT_LEAST_ONE_HOUR')
                ->atPath('endTime')
                ->addViolation();
        }
    }

    private static function parseTime(string $value): ?\DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$time instanceof \DateTimeImmutable || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $time;
    }
}
