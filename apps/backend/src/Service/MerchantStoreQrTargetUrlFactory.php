<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;

final readonly class MerchantStoreQrTargetUrlFactory
{
    public function __construct(private string $frontendUrl)
    {
    }

    public function create(Shop $shop): string
    {
        return \sprintf(
            '%s/stores/by-qr/%s',
            rtrim($this->frontendUrl, '/'),
            rawurlencode($shop->getQrCodeToken()),
        );
    }
}
