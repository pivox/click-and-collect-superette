<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PushSubscriptionInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url(requireTld: true, protocols: ['https'])]
        public string $endpoint,

        #[Assert\NotBlank]
        public string $p256dhKey,

        #[Assert\NotBlank]
        public string $authKey,

        #[Assert\Length(max: 512)]
        public ?string $userAgent = null,
    ) {
    }
}
