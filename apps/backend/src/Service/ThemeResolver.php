<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlatformTheme;
use App\Entity\ShopTheme;
use App\Exception\PlatformThemeUnavailableException;
use App\Exception\StoreDisabledException;
use App\Exception\StoreNotFoundException;
use App\Repository\PlatformThemeRepository;
use App\Repository\ShopRepository;
use Symfony\Component\Uid\Uuid;

final readonly class ThemeResolver
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PlatformThemeRepository $platformThemeRepository,
    ) {
    }

    public function resolveForStore(string $storeId): PlatformTheme|ShopTheme
    {
        if (!Uuid::isValid($storeId)) {
            throw new StoreNotFoundException();
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new StoreNotFoundException();
        }

        if (!$shop->isActive()) {
            throw new StoreDisabledException();
        }

        $shopTheme = $shop->getTheme();
        if (null !== $shopTheme) {
            return $shopTheme;
        }

        $platformTheme = $this->platformThemeRepository->findDefault();
        if (null === $platformTheme) {
            throw new PlatformThemeUnavailableException();
        }

        return $platformTheme;
    }
}
