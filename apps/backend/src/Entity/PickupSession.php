<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PickupSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PickupSessionRepository::class)]
#[ORM\Table(name: 'pickup_sessions')]
#[ORM\UniqueConstraint(name: 'UNIQ_PICKUP_SESSIONS_ORDER', columns: ['order_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_PICKUP_SESSIONS_TOKEN', columns: ['token'])]
#[ORM\Index(name: 'IDX_PICKUP_SESSIONS_EXPIRES_AT', columns: ['expires_at'])]
class PickupSession
{
    private const TOKEN_TTL = '+24 hours';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $token;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scannedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $merchantConfirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $customerConfirmedAt = null;

    #[ORM\Column]
    private bool $used = false;

    #[ORM\Column]
    private bool $forceCompletedByMerchant = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $forceNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Order $order, ?\DateTimeImmutable $generatedAt = null)
    {
        $this->id = Uuid::v4();
        $this->order = $order;
        $this->token = Uuid::v4();
        $this->generatedAt = $generatedAt ?? new \DateTimeImmutable();
        $this->expiresAt = $this->generatedAt->modify(self::TOKEN_TTL);
        $this->createdAt = $this->generatedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getToken(): Uuid
    {
        return $this->token;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function getMerchantConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->merchantConfirmedAt;
    }

    public function getCustomerConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->customerConfirmedAt;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function isForceCompletedByMerchant(): bool
    {
        return $this->forceCompletedByMerchant;
    }

    public function getForceNote(): ?string
    {
        return $this->forceNote;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) >= $this->expiresAt;
    }

    public function scan(?\DateTimeImmutable $scannedAt = null): void
    {
        if ($this->used) {
            throw new \LogicException('PICKUP_TOKEN_ALREADY_USED');
        }

        $scannedAt ??= new \DateTimeImmutable();
        if ($this->isExpired($scannedAt)) {
            throw new \LogicException('PICKUP_TOKEN_EXPIRED');
        }

        $this->scannedAt ??= $scannedAt;
    }

    public function confirmByMerchant(?\DateTimeImmutable $confirmedAt = null): void
    {
        $this->assertScanned();
        $this->merchantConfirmedAt ??= $confirmedAt ?? new \DateTimeImmutable();
        $this->markUsedWhenFullyConfirmed();
    }

    public function confirmByCustomer(?\DateTimeImmutable $confirmedAt = null): void
    {
        $this->assertScanned();
        $this->customerConfirmedAt ??= $confirmedAt ?? new \DateTimeImmutable();
        $this->markUsedWhenFullyConfirmed();
    }

    public function forceCompleteByMerchant(string $note, ?\DateTimeImmutable $completedAt = null): void
    {
        $this->assertScanned();
        $this->merchantConfirmedAt ??= $completedAt ?? new \DateTimeImmutable();
        $this->forceCompletedByMerchant = true;
        $this->forceNote = $note;
        $this->used = true;
    }

    public function completeByCode(?\DateTimeImmutable $completedAt = null): void
    {
        $completedAt ??= new \DateTimeImmutable();
        $this->scannedAt ??= $completedAt;
        $this->merchantConfirmedAt ??= $completedAt;
        $this->customerConfirmedAt ??= $completedAt;
        $this->used = true;
    }

    private function assertScanned(): void
    {
        if (null === $this->scannedAt) {
            throw new \LogicException('PICKUP_SESSION_NOT_SCANNED');
        }

        if ($this->used) {
            throw new \LogicException('PICKUP_TOKEN_ALREADY_USED');
        }
    }

    private function markUsedWhenFullyConfirmed(): void
    {
        if (null !== $this->merchantConfirmedAt && null !== $this->customerConfirmedAt) {
            $this->used = true;
        }
    }
}
