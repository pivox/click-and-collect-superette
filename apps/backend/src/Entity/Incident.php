<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IncidentStatus;
use App\Enum\IncidentType;
use App\Repository\IncidentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'incidents')]
#[ORM\Index(name: 'IDX_INCIDENTS_ORDER', columns: ['order_id'])]
#[ORM\Index(name: 'IDX_INCIDENTS_MERCHANT', columns: ['merchant_id'])]
#[ORM\Index(name: 'IDX_INCIDENTS_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_INCIDENTS_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_INCIDENTS_CREATED_AT', columns: ['created_at'])]
class Incident
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $merchant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\Column(length: 40, enumType: IncidentType::class)]
    private IncidentType $type;

    #[ORM\Column(length: 20, enumType: IncidentStatus::class)]
    private IncidentStatus $status = IncidentStatus::Open;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    private function __construct(Order $order, IncidentType $type, \DateTimeImmutable $occurredAt)
    {
        $merchant = $order->getShop()->getOwner();
        if (null === $merchant) {
            throw new \LogicException('INCIDENT_ORDER_MERCHANT_REQUIRED');
        }

        $this->id = Uuid::v4();
        $this->order = $order;
        $this->merchant = $merchant;
        $this->customer = $order->getCustomer();
        $this->type = $type;
        $this->occurredAt = $occurredAt;
        $this->createdAt = $occurredAt;
        $this->updatedAt = $occurredAt;
    }

    public static function open(Order $order, IncidentType $type, \DateTimeImmutable $occurredAt): self
    {
        return new self($order, $type, $occurredAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function getType(): IncidentType
    {
        return $this->type;
    }

    public function getStatus(): IncidentStatus
    {
        return $this->status;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function startProcessing(\DateTimeImmutable $startedAt): void
    {
        if (IncidentStatus::Closed === $this->status) {
            throw new \LogicException('INCIDENT_ALREADY_CLOSED');
        }

        $this->status = IncidentStatus::InProgress;
        $this->updatedAt = $startedAt;
    }

    public function close(\DateTimeImmutable $closedAt): void
    {
        if (IncidentStatus::Closed === $this->status) {
            throw new \LogicException('INCIDENT_ALREADY_CLOSED');
        }

        $this->status = IncidentStatus::Closed;
        $this->closedAt = $closedAt;
        $this->updatedAt = $closedAt;
    }
}
