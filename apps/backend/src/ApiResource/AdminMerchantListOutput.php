<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\AdminCreateMerchantInput;
use App\Processor\AdminCreateMerchantProcessor;
use App\Provider\AdminMerchantCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/merchants',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['admin_merchant_list:read']],
            provider: AdminMerchantCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 20],
                    description: 'Résultats par page (défaut : 20, max : 50).',
                ),
            ],
        ),
        new Post(
            uriTemplate: '/admin/merchants',
            formats: ['json' => ['application/json']],
            input: AdminCreateMerchantInput::class,
            output: AdminMerchantOutput::class,
            normalizationContext: ['groups' => ['admin_merchant:read']],
            processor: AdminCreateMerchantProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            status: 201,
            validate: true,
        ),
    ],
)]
final readonly class AdminMerchantListOutput
{
    /**
     * @param list<AdminMerchantOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['admin_merchant_list:read'])]
        public array $items,
        #[Groups(['admin_merchant_list:read'])]
        public int $page,
        #[Groups(['admin_merchant_list:read'])]
        public int $limit,
        #[Groups(['admin_merchant_list:read'])]
        public int $total,
    ) {
    }
}
