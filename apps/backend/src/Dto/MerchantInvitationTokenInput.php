<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantInvitationTokenInput
{
    #[Assert\NotBlank]
    public string $token;

    public function __construct(string $token = '')
    {
        $this->token = trim($token);
    }
}
