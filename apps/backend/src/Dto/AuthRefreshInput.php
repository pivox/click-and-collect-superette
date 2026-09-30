<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AuthRefreshInput
{
    #[Assert\NotBlank]
    #[SerializedName('refresh_token')]
    public string $refreshToken;

    public function __construct(string $refreshToken = '')
    {
        $this->refreshToken = trim($refreshToken);
    }
}
