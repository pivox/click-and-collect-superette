<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminMerchantOnboardingInput
{
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?AdminMerchantOnboardingMerchantInput $merchant = null;

    #[Assert\NotNull]
    #[Assert\Valid]
    public ?AdminMerchantOnboardingShopInput $shop = null;

    #[Assert\NotBlank]
    #[Assert\Choice(['temporary_password', 'email_invitation'])]
    #[SerializedName('first_login_mode')]
    public string $firstLoginMode = 'temporary_password';

    /**
     * @var list<string>
     */
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Uuid(),
    ])]
    #[SerializedName('product_group_ids')]
    public array $productGroupIds = [];
}
