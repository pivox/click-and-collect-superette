<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Processor\CreateKadhiaShareLinkProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/me/kadhias/{kadhiaId}/share-links',
            uriVariables: [
                'kadhiaId' => new Link(fromClass: KadhiaShareLinkOutput::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['kadhia_share_link:read']],
            status: 200,
            input: false,
            read: false,
            processor: CreateKadhiaShareLinkProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class KadhiaShareLinkOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(['kadhia_share_link:read'])]
        #[SerializedName('share_url')]
        public string $shareUrl,
        #[Groups(['kadhia_share_link:read'])]
        #[SerializedName('expires_at')]
        public string $expiresAt,
    ) {
    }
}
