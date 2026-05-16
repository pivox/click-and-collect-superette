<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MerchantExceptionalClosurePatchInput
{
    #[SerializedName('starts_at')]
    public ?\DateTimeImmutable $startsAt = null;

    #[SerializedName('ends_at')]
    public ?\DateTimeImmutable $endsAt = null;

    #[Assert\Length(max: 255)]
    public ?string $reason = null;

    #[Assert\Callback]
    public function validateCompleteRange(ExecutionContextInterface $context): void
    {
        if (null === $this->startsAt || null === $this->endsAt) {
            return;
        }

        if ($this->startsAt >= $this->endsAt) {
            $context->buildViolation('EXCEPTIONAL_CLOSURE_STARTS_AT_MUST_BE_BEFORE_ENDS_AT')
                ->atPath('startsAt')
                ->addViolation();
        }
    }
}
