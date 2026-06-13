<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class FeedbackCreateInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(['bug', 'idea', 'confusing', 'other'])]
    public ?string $type = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 2000)]
    public ?string $message = null;

    #[Assert\NotBlank]
    #[Assert\Choice(['client', 'merchant', 'admin'])]
    public ?string $appArea = null;

    #[Assert\Length(max: 100)]
    public ?string $appSubArea = null;

    #[Assert\Length(max: 2048)]
    public ?string $pageUrl = null;

    #[Assert\Length(max: 150)]
    public ?string $routeName = null;

    #[Assert\Length(max: 255)]
    public ?string $pageTitle = null;

    #[Assert\Length(max: 10)]
    public ?string $locale = null;

    #[Assert\Positive]
    public ?int $viewportWidth = null;

    #[Assert\Positive]
    public ?int $viewportHeight = null;

    #[Assert\Uuid]
    public ?string $shopId = null;

    public bool $contactConsent = false;
}
