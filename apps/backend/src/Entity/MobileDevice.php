<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MobileApplication;
use App\Enum\MobileDeviceRevocationReason;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use App\Repository\MobileDeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * MOBILE-PUSH #620: native mobile push device (Expo token), coexisting with
 * the PWA `push_subscriptions` table (no migration — decision §2.2 of
 * docs/mobile/push-and-links.md).
 *
 * The Expo push token is stored IN CLEAR: it is required to call the push
 * provider (a hash alone could not address the device) and is protected by
 * database access; `push_token_hash` (sha256, unique) is the upsert key, the
 * same motif as PushSubscription.endpoint_hash. No IMEI, serial or
 * advertising identifier is ever stored — the revocable push token is the
 * only device identifier (minimisation, decisions 9/10).
 */
#[ORM\Entity(repositoryClass: MobileDeviceRepository::class)]
#[ORM\Table(name: 'mobile_devices')]
#[ORM\UniqueConstraint(name: 'UNIQ_MOBILE_DEVICES_TOKEN_HASH', columns: ['push_token_hash'])]
#[ORM\Index(name: 'IDX_MOBILE_DEVICES_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_MOBILE_DEVICES_USER_APP_ENABLED', columns: ['user_id', 'application', 'enabled'])]
class MobileDevice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: MobileApplication::class)]
    private MobileApplication $application;

    #[ORM\Column(length: 16, enumType: MobilePlatform::class)]
    private MobilePlatform $platform;

    #[ORM\Column(length: 16, enumType: MobilePushProvider::class)]
    private MobilePushProvider $provider;

    #[ORM\Column(name: 'push_token', type: 'text')]
    private string $pushToken;

    #[ORM\Column(name: 'push_token_hash', type: 'string', length: 64)]
    private string $pushTokenHash;

    #[ORM\Column(length: 8)]
    private string $locale;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $timezone;

    #[ORM\Column(name: 'app_version', length: 32, nullable: true)]
    private ?string $appVersion;

    #[ORM\Column(name: 'os_major_version', length: 8, nullable: true)]
    private ?string $osMajorVersion;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'revocation_reason', length: 32, nullable: true, enumType: MobileDeviceRevocationReason::class)]
    private ?MobileDeviceRevocationReason $revocationReason = null;

    #[ORM\Column(name: 'last_success_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(name: 'last_failure_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastFailureAt = null;

    #[ORM\Column(name: 'failure_count', type: 'integer')]
    private int $failureCount = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $user,
        MobileApplication $application,
        MobilePlatform $platform,
        MobilePushProvider $provider,
        string $pushToken,
        string $locale = 'fr',
        ?string $timezone = null,
        ?string $appVersion = null,
        ?string $osMajorVersion = null,
    ) {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->application = $application;
        $this->platform = $platform;
        $this->provider = $provider;
        $this->pushToken = $pushToken;
        $this->pushTokenHash = hash('sha256', $pushToken);
        $this->locale = $locale;
        $this->timezone = $timezone;
        $this->appVersion = $appVersion;
        $this->osMajorVersion = $osMajorVersion;
        $now = new \DateTimeImmutable();
        $this->lastSeenAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Upsert by push_token_hash (decision 2): a known token re-appearing is
     * reassigned to the newly authenticated user, re-enabled and un-revoked —
     * a physical device only ever follows its current user.
     */
    public function refresh(
        User $user,
        MobileApplication $application,
        MobilePlatform $platform,
        MobilePushProvider $provider,
        string $locale,
        ?string $timezone,
        ?string $appVersion,
        ?string $osMajorVersion,
    ): self {
        $this->user = $user;
        $this->application = $application;
        $this->platform = $platform;
        $this->provider = $provider;
        $this->locale = $locale;
        $this->timezone = $timezone;
        $this->appVersion = $appVersion;
        $this->osMajorVersion = $osMajorVersion;
        $this->enabled = true;
        $this->revokedAt = null;
        $this->revocationReason = null;
        $this->failureCount = 0;
        $now = new \DateTimeImmutable();
        $this->lastSeenAt = $now;
        $this->updatedAt = $now;

        return $this;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getApplication(): MobileApplication
    {
        return $this->application;
    }

    public function getPlatform(): MobilePlatform
    {
        return $this->platform;
    }

    public function getProvider(): MobilePushProvider
    {
        return $this->provider;
    }

    public function getPushToken(): string
    {
        return $this->pushToken;
    }

    public function getPushTokenHash(): string
    {
        return $this->pushTokenHash;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $appVersion): self
    {
        $this->appVersion = $appVersion;

        return $this;
    }

    public function getOsMajorVersion(): ?string
    {
        return $this->osMajorVersion;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function markSeen(): self
    {
        $now = new \DateTimeImmutable();
        $this->lastSeenAt = $now;
        $this->updatedAt = $now;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevocationReason(): ?MobileDeviceRevocationReason
    {
        return $this->revocationReason;
    }

    /**
     * Logical revocation (idempotent): a device already revoked keeps its
     * original timestamp and reason.
     */
    public function revoke(MobileDeviceRevocationReason $reason): self
    {
        if (null !== $this->revokedAt) {
            return $this;
        }

        $now = new \DateTimeImmutable();
        $this->revokedAt = $now;
        $this->revocationReason = $reason;
        $this->updatedAt = $now;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->enabled && null === $this->revokedAt;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function recordSendSuccess(): self
    {
        $now = new \DateTimeImmutable();
        $this->lastSuccessAt = $now;
        $this->updatedAt = $now;

        return $this;
    }

    public function getLastFailureAt(): ?\DateTimeImmutable
    {
        return $this->lastFailureAt;
    }

    public function recordSendFailure(): self
    {
        $now = new \DateTimeImmutable();
        $this->lastFailureAt = $now;
        ++$this->failureCount;
        $this->updatedAt = $now;

        return $this;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
