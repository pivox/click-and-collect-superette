<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;

final readonly class MerchantStoreQrTargetUrlFactory
{
    public function __construct(private FrontendUrlBuilder $frontendUrlBuilder)
    {
    }

    public function create(Shop $shop): string
    {
        return $this->frontendUrlBuilder->build('/stores/by-qr/'.rawurlencode($shop->getQrCodeToken()));
    }
}
