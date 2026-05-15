<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PasswordResetRequestInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    public function __construct(string $email = '')
    {
        $this->email = trim($email);
    }
}
