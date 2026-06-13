<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminFeedbackStatusInput
{
    #[Assert\Length(max: 2000)]
    public ?string $adminNote = null;
}
