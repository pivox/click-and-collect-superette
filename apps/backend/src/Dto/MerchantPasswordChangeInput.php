<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Password change for the current merchant: the current password must be
 * provided and verified before the new one is hashed and stored.
 */
final readonly class MerchantPasswordChangeInput
{
    #[Assert\NotBlank]
    #[SerializedName('current_password')]
    public string $currentPassword;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, minMessage: 'AUTH_WEAK_PASSWORD')]
    #[SerializedName('new_password')]
    public string $newPassword;

    public function __construct(string $currentPassword = '', string $newPassword = '')
    {
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
    }
}
