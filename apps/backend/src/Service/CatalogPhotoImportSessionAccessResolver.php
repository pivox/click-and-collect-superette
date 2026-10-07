<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CatalogPhotoImportSession;
use App\Entity\Shop;
use App\Repository\CatalogPhotoImportSessionRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Shared "load shop, check merchant access, load session scoped to that
 * shop" sequence used by every photo-import-session endpoint
 * (CATALOG-AI-001, issue #638).
 */
final readonly class CatalogPhotoImportSessionAccessResolver
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CatalogPhotoImportSessionRepository $sessionRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    public function resolveShop(string $storeId): Shop
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        return $shop;
    }

    public function resolveSession(string $storeId, string $sessionId): CatalogPhotoImportSession
    {
        $shop = $this->resolveShop($storeId);
        $session = $this->sessionRepository->findOneForShop($shop, $sessionId);
        if (null === $session) {
            throw new NotFoundHttpException('CATALOG_PHOTO_IMPORT_SESSION_NOT_FOUND');
        }

        return $session;
    }
}
