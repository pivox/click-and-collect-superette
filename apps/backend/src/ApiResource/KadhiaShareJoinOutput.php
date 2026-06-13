<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Processor\JoinKadhiaShareLinkProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/me/kadhia-share-links/{token}/join',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['kadhia_share_join:read']],
            status: 200,
            input: false,
            read: false,
            processor: JoinKadhiaShareLinkProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class KadhiaShareJoinOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $token,
        #[Groups(['kadhia_share_join:read'])]
        #[SerializedName('kadhia_id')]
        public string $kadhiaId,
        #[Groups(['kadhia_share_join:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['kadhia_share_join:read'])]
        public bool $joined,
    ) {
    }
}
