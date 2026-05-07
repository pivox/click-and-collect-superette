<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\StoreThemeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/stores/{storeId}/theme',
            formats: ['json' => ['application/json']],
            provider: StoreThemeProvider::class,
            cacheHeaders: [
                'public' => true,
                'max_age' => 300,
            ],
            normalizationContext: ['groups' => ['store_theme:read']],
            security: "is_granted('PUBLIC_ACCESS')",
        ),
    ],
)]
final readonly class ResolvedThemeOutput
{
    public function __construct(
        #[Groups(['store_theme:read'])]
        #[SerializedName('--color-primary')]
        public string $colorPrimary,
        #[Groups(['store_theme:read'])]
        #[SerializedName('--color-secondary')]
        public string $colorSecondary,
        #[Groups(['store_theme:read'])]
        #[SerializedName('--color-accent')]
        public string $colorAccent,
        #[Groups(['store_theme:read'])]
        #[SerializedName('--color-text')]
        public string $colorText,
        #[Groups(['store_theme:read'])]
        #[SerializedName('--color-background')]
        public string $colorBackground,
        #[Groups(['store_theme:read'])]
        #[SerializedName('--font-family')]
        public string $fontFamily,
        #[Groups(['store_theme:read'])]
        #[SerializedName('--font-size-base')]
        public string $fontSizeBase,
        #[ApiProperty(identifier: true)]
        public ?string $storeId = null,
    ) {
    }
}
