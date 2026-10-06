<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SocialAuthExchangeInput
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 100)] public string $code = '',
        #[Assert\NotBlank] #[Assert\Length(max: 100)] public string $state = '',
        #[SerializedName('code_verifier')]
        #[Assert\Regex('/^[A-Za-z0-9._~-]{43,128}$/D')]
        #[Assert\NotBlank]
        public string $codeVerifier = '',
    ) {
    }
}
