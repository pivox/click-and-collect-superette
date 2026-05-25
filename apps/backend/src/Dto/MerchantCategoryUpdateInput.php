<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class MerchantCategoryUpdateInput
{
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: 160)]
    #[SerializedName('name_fr')]
    public ?string $nameFr = null;

    #[Assert\Length(max: 160)]
    private ?string $nameAr = null;

    #[Assert\Uuid]
    private ?string $parentId = null;

    #[SerializedName('sort_order')]
    public ?int $sortOrder = null;

    public ?bool $active = null;

    private bool $nameArProvided = false;

    private bool $parentIdProvided = false;

    public function getNameAr(): ?string
    {
        return $this->nameAr;
    }

    #[SerializedName('name_ar')]
    public function setNameAr(?string $nameAr): void
    {
        $this->nameAr = $nameAr;
        $this->nameArProvided = true;
    }

    public function hasNameAr(): bool
    {
        return $this->nameArProvided;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    #[SerializedName('parent_id')]
    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
        $this->parentIdProvided = true;
    }

    public function hasParentId(): bool
    {
        return $this->parentIdProvided;
    }
}
