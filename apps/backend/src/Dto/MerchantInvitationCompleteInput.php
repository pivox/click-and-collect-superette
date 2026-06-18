<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantInvitationCompleteInput
{
    #[Assert\NotBlank]
    public string $token;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, minMessage: 'AUTH_WEAK_PASSWORD')]
    #[SerializedName('new_password')]
    public string $newPassword;

    #[Assert\NotBlank]
    #[SerializedName('new_password_confirmation')]
    public string $newPasswordConfirmation;

    public function __construct(
        string $token = '',
        string $newPassword = '',
        string $newPasswordConfirmation = '',
    ) {
        $this->token = trim($token);
        $this->newPassword = $newPassword;
        $this->newPasswordConfirmation = $newPasswordConfirmation;
    }
}
