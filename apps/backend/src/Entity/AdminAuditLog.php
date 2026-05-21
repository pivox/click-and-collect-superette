<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdminAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdminAuditLogRepository::class)]
#[ORM\Table(name: 'admin_audit_logs')]
#[ORM\Index(name: 'IDX_ADMIN_AUDIT_LOGS_ACTION', columns: ['action'])]
#[ORM\Index(name: 'IDX_ADMIN_AUDIT_LOGS_RESOURCE', columns: ['resource_type', 'resource_id'])]
#[ORM\Index(name: 'IDX_ADMIN_AUDIT_LOGS_CREATED_AT', columns: ['created_at'])]
class AdminAuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $adminUser;

    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\Column(name: 'resource_type', length: 100)]
    private string $resourceType;

    #[ORM\Column(name: 'resource_id', length: 36)]
    private string $resourceId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        User $adminUser,
        string $action,
        string $resourceType,
        string $resourceId,
        ?string $summary = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->id = Uuid::v4();
        $this->adminUser = $adminUser;
        $this->action = $action;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->summary = $summary;
        $this->metadata = $metadata;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAdminUser(): User
    {
        return $this->adminUser;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
