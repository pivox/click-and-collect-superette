<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SocialAuthStartInput
{
    public function __construct(
        #[Assert\Choice(['google', 'facebook'])] public string $provider = '',
        #[Assert\Choice(['login', 'link'])] public string $mode = 'login',
        #[SerializedName('code_challenge')]
        #[Assert\Regex('/^[A-Za-z0-9_-]{43}$/D')]
        #[Assert\NotBlank]
        public string $codeChallenge = '',
    ) {
    }
}
