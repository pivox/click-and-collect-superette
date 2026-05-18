<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Shop;

final readonly class AdminStoreOutputFactory
{
    public function create(
        Shop $shop,
        int $productsCount,
        int $exceptionalClosuresCount = 0,
        int $pickupRulesCount = 0,
    ): AdminStoreOutput {
        $owner = $shop->getOwner();
        $theme = $shop->getTheme();

        return new AdminStoreOutput(
            id: $shop->getId()->toRfc4122(),
            name: $shop->getName(),
            slug: $shop->getSlug(),
            address: $shop->getAddress(),
            city: $shop->getCity(),
            phone: $shop->getPhone(),
            isActive: $shop->isActive(),
            qrCodeToken: $shop->getQrCodeToken(),
            createdAt: $shop->getCreatedAt()->format(\DateTimeInterface::ATOM),
            owner: null === $owner ? null : new AdminStoreOwnerOutput(
                id: $owner->getId()->toRfc4122(),
                email: $owner->getEmail(),
            ),
            productsCount: $productsCount,
            themeId: null === $theme ? null : $theme->getId()->toRfc4122(),
            openingHours: $shop->getOpeningHours(),
            exceptionalClosuresCount: $exceptionalClosuresCount,
            pickupRulesCount: $pickupRulesCount,
        );
    }
}
