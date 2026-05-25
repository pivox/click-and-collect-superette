<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\MerchantCategoryOutput;
use App\Entity\MerchantCategory;

final readonly class MerchantCategoryMapper
{
    public function toOutput(MerchantCategory $merchantCategory): MerchantCategoryOutput
    {
        return new MerchantCategoryOutput(
            id: $merchantCategory->getId()->toRfc4122(),
            nameFr: $merchantCategory->getNameFr(),
            nameAr: $merchantCategory->getNameAr(),
            slug: $merchantCategory->getSlug(),
            parentId: $merchantCategory->getParent()?->getId()->toRfc4122(),
            sortOrder: $merchantCategory->getSortOrder(),
            active: $merchantCategory->isActive(),
            createdAt: $merchantCategory->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $merchantCategory->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
