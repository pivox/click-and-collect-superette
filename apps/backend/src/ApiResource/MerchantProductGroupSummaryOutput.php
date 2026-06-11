<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MerchantProductGroupSummaryOutput
{
    public function __construct(
        #[Groups(['merchant_product_group_list:read'])]
        public string $id,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('name_fr')]
        public string $nameFr,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('name_ar')]
        public ?string $nameAr,
        #[Groups(['merchant_product_group_list:read'])]
        public string $slug,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('description_fr')]
        public ?string $descriptionFr,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('description_ar')]
        public ?string $descriptionAr,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('market_country')]
        public string $marketCountry,
        #[Groups(['merchant_product_group_list:read'])]
        public ?string $icon,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('sort_order')]
        public int $sortOrder,
        #[Groups(['merchant_product_group_list:read'])]
        #[SerializedName('items_count')]
        public int $itemsCount,
    ) {
    }
}
