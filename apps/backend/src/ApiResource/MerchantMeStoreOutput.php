<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\Shop;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MerchantMeStoreOutput
{
    public function __construct(
        #[Groups(['merchant_me:read'])]
        public string $id,
        #[Groups(['merchant_me:read'])]
        public string $name,
        #[Groups(['merchant_me:read'])]
        public bool $active,
    ) {
    }

    public static function fromShop(Shop $shop): self
    {
        return new self(
            id: $shop->getId()->toRfc4122(),
            name: $shop->getName(),
            active: $shop->isActive(),
        );
    }
}
