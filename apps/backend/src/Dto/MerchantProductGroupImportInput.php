<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class MerchantProductGroupImportInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $groupId = '';

    /**
     * @var list<string>
     */
    #[Assert\Count(min: 1, max: 200)]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Uuid(),
    ])]
    public array $selectedProductReferenceIds = [];

    #[Assert\Type('bool')]
    public mixed $skipExisting = true;

    #[Assert\Type('bool')]
    public mixed $defaultVisibility = false;

    #[Assert\Type('bool')]
    public mixed $defaultAvailability = true;
}
