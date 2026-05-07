<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Dto\ThemeWriteInput;
use App\Processor\UpdateMerchantShopThemeProcessor;
use App\Provider\MerchantShopThemeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/theme',
            formats: ['json' => ['application/json']],
            provider: MerchantShopThemeProvider::class,
            normalizationContext: ['groups' => ['merchant_shop_theme:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Put(
            uriTemplate: '/merchant/stores/{storeId}/theme',
            formats: ['json' => ['application/json']],
            input: ThemeWriteInput::class,
            output: self::class,
            read: false,
            processor: UpdateMerchantShopThemeProcessor::class,
            normalizationContext: ['groups' => ['merchant_shop_theme:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantShopThemeOutput
{
    /**
     * @param list<ThemeWarningOutput> $warnings
     */
    public function __construct(
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('primary_color')]
        public string $primaryColor,
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('secondary_color')]
        public string $secondaryColor,
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('accent_color')]
        public string $accentColor,
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('text_color')]
        public string $textColor,
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('background_color')]
        public string $backgroundColor,
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('font_family')]
        public string $fontFamily,
        #[Groups(['merchant_shop_theme:read'])]
        #[SerializedName('base_font_size')]
        public int $baseFontSize,
        #[Groups(['merchant_shop_theme:read'])]
        public array $warnings = [],
        #[ApiProperty(identifier: true)]
        public ?string $storeId = null,
    ) {
    }
}
