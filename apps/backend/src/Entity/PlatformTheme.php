<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ThemeFontFamily;
use App\Repository\PlatformThemeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlatformThemeRepository::class)]
#[ORM\Table(name: 'platform_themes')]
#[ORM\UniqueConstraint(name: 'UNIQ_PLATFORM_THEME_SINGLETON', columns: ['singleton_key'])]
#[ORM\HasLifecycleCallbacks]
class PlatformTheme
{
    public const DEFAULT_ID = '00000000-0000-0000-0000-000000000004';
    public const DEFAULT_SINGLETON_KEY = 'default';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 32)]
    private string $singletonKey = self::DEFAULT_SINGLETON_KEY;

    #[ORM\Column(length: 7)]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $primaryColor = '#1B6CA8';

    #[ORM\Column(length: 7)]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $secondaryColor = '#F0A500';

    #[ORM\Column(length: 7)]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $accentColor = '#E63946';

    #[ORM\Column(length: 7)]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $textColor = '#1A1A1A';

    #[ORM\Column(length: 7)]
    #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/')]
    private string $backgroundColor = '#FFFFFF';

    #[ORM\Column(length: 32, enumType: ThemeFontFamily::class)]
    private ThemeFontFamily $fontFamily = ThemeFontFamily::Inter;

    #[ORM\Column]
    #[Assert\Range(min: 14, max: 20)]
    private int $baseFontSize = 16;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSingletonKey(): string
    {
        return $this->singletonKey;
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(string $primaryColor): static
    {
        $this->primaryColor = $primaryColor;

        return $this;
    }

    public function getSecondaryColor(): string
    {
        return $this->secondaryColor;
    }

    public function setSecondaryColor(string $secondaryColor): static
    {
        $this->secondaryColor = $secondaryColor;

        return $this;
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function setAccentColor(string $accentColor): static
    {
        $this->accentColor = $accentColor;

        return $this;
    }

    public function getTextColor(): string
    {
        return $this->textColor;
    }

    public function setTextColor(string $textColor): static
    {
        $this->textColor = $textColor;

        return $this;
    }

    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(string $backgroundColor): static
    {
        $this->backgroundColor = $backgroundColor;

        return $this;
    }

    public function getFontFamily(): ThemeFontFamily
    {
        return $this->fontFamily;
    }

    public function setFontFamily(ThemeFontFamily $fontFamily): static
    {
        $this->fontFamily = $fontFamily;

        return $this;
    }

    public function getBaseFontSize(): int
    {
        return $this->baseFontSize;
    }

    public function setBaseFontSize(int $baseFontSize): static
    {
        $this->baseFontSize = $baseFontSize;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
