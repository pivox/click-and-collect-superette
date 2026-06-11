<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: \App\Repository\PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscriptions')]
#[ORM\UniqueConstraint(name: 'UNIQ_PUSH_ENDPOINT', columns: ['endpoint_hash'])]
#[ORM\Index(name: 'IDX_PUSH_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_PUSH_SCOPE', columns: ['scope'])]
final class PushSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $endpoint;

    #[ORM\Column(name: 'p256dh_key', type: 'text')]
    private string $p256dhKey;

    #[ORM\Column(name: 'auth_key', type: 'text')]
    private string $authKey;

    #[ORM\Column(name: 'endpoint_hash', type: 'string', length: 64)]
    private string $endpointHash;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $scope = null;

    #[ORM\Column(name: 'user_agent', type: 'string', length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'last_success_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(name: 'last_failure_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastFailureAt = null;

    #[ORM\Column(name: 'failure_count', type: 'integer')]
    private int $failureCount = 0;

    public function __construct(
        User $user,
        string $endpoint,
        string $p256dhKey,
        string $authKey,
        ?string $scope = null,
        ?string $userAgent = null,
    ) {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->p256dhKey = $p256dhKey;
        $this->authKey = $authKey;
        $this->endpointHash = hash('sha256', $endpoint);
        $this->scope = $scope;
        $this->userAgent = $userAgent;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function refresh(
        User $user,
        string $endpoint,
        string $p256dhKey,
        string $authKey,
        ?string $scope,
        ?string $userAgent,
    ): self {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->p256dhKey = $p256dhKey;
        $this->authKey = $authKey;
        $this->endpointHash = hash('sha256', $endpoint);
        $this->scope = $scope;
        $this->userAgent = $userAgent;
        $this->updatedAt = new \DateTimeImmutable();
        $this->failureCount = 0;

        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getP256dhKey(): string
    {
        return $this->p256dhKey;
    }

    public function getAuthKey(): string
    {
        return $this->authKey;
    }

    public function getEndpointHash(): string
    {
        return $this->endpointHash;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function setLastSuccessAt(\DateTimeImmutable $lastSuccessAt): self
    {
        $this->lastSuccessAt = $lastSuccessAt;

        return $this;
    }

    public function getLastFailureAt(): ?\DateTimeImmutable
    {
        return $this->lastFailureAt;
    }

    public function setLastFailureAt(\DateTimeImmutable $lastFailureAt): self
    {
        $this->lastFailureAt = $lastFailureAt;

        return $this;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function setFailureCount(int $failureCount): self
    {
        $this->failureCount = $failureCount;

        return $this;
    }
}
