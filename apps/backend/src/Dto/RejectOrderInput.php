<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RejectOrderInput
{
    #[Assert\Length(max: 500)]
    public ?string $reason;

    public function __construct(?string $reason = null)
    {
        $reason = null !== $reason ? trim($reason) : null;
        $this->reason = null === $reason || '' === $reason ? null : $reason;
    }
}
