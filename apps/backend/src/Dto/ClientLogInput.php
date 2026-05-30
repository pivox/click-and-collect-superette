<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ClientLogInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['debug', 'info', 'warning', 'error'])]
    public ?string $level = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $event = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public ?string $message = null;

    /** @var array<string, mixed>|null */
    public ?array $context = null;

    #[Assert\Length(max: 50)]
    public ?string $appVersion = null;

    #[Assert\Length(max: 50)]
    public ?string $environment = null;

    #[Assert\Length(max: 500)]
    public ?string $url = null;

    #[Assert\Length(max: 50)]
    public ?string $createdAt = null;
}
