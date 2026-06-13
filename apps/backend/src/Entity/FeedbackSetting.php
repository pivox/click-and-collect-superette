<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FeedbackAppArea;
use App\Repository\FeedbackSettingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FeedbackSettingRepository::class)]
#[ORM\Table(name: 'feedback_settings')]
class FeedbackSetting
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'global_enabled')]
    private bool $globalEnabled = false;

    #[ORM\Column(name: 'client_enabled')]
    private bool $clientEnabled = false;

    #[ORM\Column(name: 'merchant_enabled')]
    private bool $merchantEnabled = false;

    #[ORM\Column(name: 'admin_enabled')]
    private bool $adminEnabled = false;

    #[ORM\Column(name: 'client_area_enabled')]
    private bool $clientAreaEnabled = false;

    #[ORM\Column(name: 'merchant_area_enabled')]
    private bool $merchantAreaEnabled = false;

    #[ORM\Column(name: 'admin_area_enabled')]
    private bool $adminAreaEnabled = false;

    #[ORM\Column(name: 'require_authenticated_user')]
    private bool $requireAuthenticatedUser = true;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function isGlobalEnabled(): bool
    {
        return $this->globalEnabled;
    }

    public function isClientEnabled(): bool
    {
        return $this->clientEnabled;
    }

    public function isMerchantEnabled(): bool
    {
        return $this->merchantEnabled;
    }

    public function isAdminEnabled(): bool
    {
        return $this->adminEnabled;
    }

    public function isClientAreaEnabled(): bool
    {
        return $this->clientAreaEnabled;
    }

    public function isMerchantAreaEnabled(): bool
    {
        return $this->merchantAreaEnabled;
    }

    public function isAdminAreaEnabled(): bool
    {
        return $this->adminAreaEnabled;
    }

    public function requiresAuthenticatedUser(): bool
    {
        return $this->requireAuthenticatedUser;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isEnabledFor(string $userRole, FeedbackAppArea $appArea): bool
    {
        if (!$this->globalEnabled || !$this->requireAuthenticatedUser) {
            return false;
        }

        $roleEnabled = match ($userRole) {
            'client' => $this->clientEnabled,
            'merchant' => $this->merchantEnabled,
            'admin' => $this->adminEnabled,
            default => false,
        };

        if (!$roleEnabled) {
            return false;
        }

        return match ($appArea) {
            FeedbackAppArea::Client => $this->clientAreaEnabled,
            FeedbackAppArea::Merchant => $this->merchantAreaEnabled,
            FeedbackAppArea::Admin => $this->adminAreaEnabled,
        };
    }

    public function update(
        bool $globalEnabled,
        bool $clientEnabled,
        bool $merchantEnabled,
        bool $adminEnabled,
        bool $clientAreaEnabled,
        bool $merchantAreaEnabled,
        bool $adminAreaEnabled,
        bool $requireAuthenticatedUser = true,
    ): void {
        $this->globalEnabled = $globalEnabled;
        $this->clientEnabled = $clientEnabled;
        $this->merchantEnabled = $merchantEnabled;
        $this->adminEnabled = $adminEnabled;
        $this->clientAreaEnabled = $clientAreaEnabled;
        $this->merchantAreaEnabled = $merchantAreaEnabled;
        $this->adminAreaEnabled = $adminAreaEnabled;
        $this->requireAuthenticatedUser = $requireAuthenticatedUser;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
