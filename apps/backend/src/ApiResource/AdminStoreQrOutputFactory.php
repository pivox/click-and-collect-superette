<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Shop;

final readonly class AdminStoreQrOutputFactory
{
    public function create(Shop $shop): AdminStoreQrOutput
    {
        $targetUrl = \sprintf('/api/stores/by-qr/%s', $shop->getQrCodeToken());

        return new AdminStoreQrOutput(
            storeId: $shop->getId()->toRfc4122(),
            storeName: $shop->getName(),
            slug: $shop->getSlug(),
            qrCodeToken: $shop->getQrCodeToken(),
            targetUrl: $targetUrl,
            qrPayload: $targetUrl,
        );
    }
}
