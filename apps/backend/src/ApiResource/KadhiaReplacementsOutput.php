<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Provider\KadhiaReplacementsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/kadhias/{kadhiaId}/replacements',
            uriVariables: [
                'kadhiaId' => new Link(fromClass: KadhiaReplacementsOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['kadhia_replacements:read', 'store_catalog:read']],
            provider: KadhiaReplacementsProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class KadhiaReplacementsOutput
{
    /**
     * @param list<KadhiaReplacementLineOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['kadhia_replacements:read'])]
        public string $id,
        #[Groups(['kadhia_replacements:read'])]
        public array $items,
    ) {
    }
}
