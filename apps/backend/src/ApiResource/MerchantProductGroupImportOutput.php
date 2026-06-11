<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantProductGroupImportInput;
use App\Entity\Shop;
use App\Processor\MerchantProductGroupImportProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/catalog/import-from-product-group',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MerchantProductGroupImportInput::class,
            output: self::class,
            status: 200,
            read: false,
            normalizationContext: ['groups' => ['merchant_product_group_import:read']],
            processor: MerchantProductGroupImportProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantProductGroupImportOutput
{
    /**
     * @param list<MerchantProductGroupImportErrorOutput> $errors
     */
    public function __construct(
        #[Groups(['merchant_product_group_import:read'])]
        public int $created,
        #[Groups(['merchant_product_group_import:read'])]
        public int $alreadyInCatalog,
        #[Groups(['merchant_product_group_import:read'])]
        public int $skipped,
        #[Groups(['merchant_product_group_import:read'])]
        public int $requiresPriceCompletion,
        #[Groups(['merchant_product_group_import:read'])]
        public array $errors,
    ) {
    }
}
