<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'IDX_NOTIFICATIONS_USER_READ_CREATED', columns: ['user_id', 'is_read', 'created_at'])]
#[ORM\Index(name: 'IDX_NOTIFICATIONS_ORDER', columns: ['order_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_NOTIFICATIONS_ORDER_TYPE', columns: ['order_id', 'type'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Order $order;

    #[ORM\Column(length: 120)]
    private string $titleFr;

    #[ORM\Column(length: 120)]
    private string $titleAr;

    #[ORM\Column(length: 500)]
    private string $bodyFr;

    #[ORM\Column(length: 500)]
    private string $bodyAr;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $type;

    #[ORM\Column(name: 'is_read')]
    private bool $read = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
        ?Order $order = null,
        ?string $type = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->titleFr = $titleFr;
        $this->titleAr = $titleAr;
        $this->bodyFr = $bodyFr;
        $this->bodyAr = $bodyAr;
        $this->order = $order;
        $this->type = $type;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getTitleFr(): string
    {
        return $this->titleFr;
    }

    public function getTitleAr(): string
    {
        return $this->titleAr;
    }

    public function getBodyFr(): string
    {
        return $this->bodyFr;
    }

    public function getBodyAr(): string
    {
        return $this->bodyAr;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function markRead(): void
    {
        $this->read = true;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
