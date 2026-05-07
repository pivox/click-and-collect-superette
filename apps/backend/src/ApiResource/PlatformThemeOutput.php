<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Dto\ThemeWriteInput;
use App\Processor\UpdatePlatformThemeProcessor;
use App\Provider\PlatformThemeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/theme',
            formats: ['json' => ['application/json']],
            provider: PlatformThemeProvider::class,
            normalizationContext: ['groups' => ['admin_theme:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Put(
            uriTemplate: '/admin/theme',
            formats: ['json' => ['application/json']],
            input: ThemeWriteInput::class,
            output: self::class,
            processor: UpdatePlatformThemeProcessor::class,
            normalizationContext: ['groups' => ['admin_theme:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class PlatformThemeOutput
{
    /**
     * @param list<ThemeWarningOutput> $warnings
     */
    public function __construct(
        #[Groups(['admin_theme:read'])]
        #[SerializedName('primary_color')]
        public string $primaryColor,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('secondary_color')]
        public string $secondaryColor,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('accent_color')]
        public string $accentColor,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('text_color')]
        public string $textColor,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('background_color')]
        public string $backgroundColor,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('font_family')]
        public string $fontFamily,
        #[Groups(['admin_theme:read'])]
        #[SerializedName('base_font_size')]
        public int $baseFontSize,
        #[Groups(['admin_theme:read'])]
        public array $warnings = [],
    ) {
    }
}
