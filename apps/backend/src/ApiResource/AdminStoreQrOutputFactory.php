<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Shop;

final readonly class AdminStoreQrOutputFactory
{
    public function __construct(private \App\Service\MerchantStoreQrTargetUrlFactory $targetUrlFactory)
    {
    }

    public function create(Shop $shop): AdminStoreQrOutput
    {
        return new AdminStoreQrOutput(
            storeId: $shop->getId()->toRfc4122(),
            storeName: $shop->getName(),
            slug: $shop->getSlug(),
            qrCodeToken: $shop->getQrCodeToken(),
            targetUrl: $this->targetUrlFactory->create($shop),
        );
    }
}
