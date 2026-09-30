<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlatformSettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PlatformSettingRepository::class)]
#[ORM\Table(name: 'platform_settings')]
#[ORM\UniqueConstraint(name: 'UNIQ_PLATFORM_SETTING_SINGLETON', columns: ['singleton_key'])]
class PlatformSetting
{
    public const DEFAULT_SINGLETON_KEY = 'default';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 32)]
    private string $singletonKey = self::DEFAULT_SINGLETON_KEY;

    #[ORM\Column(length: 2048)]
    private string $frontendOrigin;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $frontendOrigin)
    {
        $this->id = Uuid::v4();
        $this->frontendOrigin = $frontendOrigin;
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

    public function getFrontendOrigin(): string
    {
        return $this->frontendOrigin;
    }

    public function updateFrontendOrigin(string $frontendOrigin): void
    {
        $this->frontendOrigin = $frontendOrigin;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
