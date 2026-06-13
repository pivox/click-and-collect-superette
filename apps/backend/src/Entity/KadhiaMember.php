<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\KadhiaMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: KadhiaMemberRepository::class)]
#[ORM\Table(name: 'kadhia_members')]
#[ORM\UniqueConstraint(name: 'UNIQ_KADHIA_MEMBERS_KADHIA_USER', columns: ['kadhia_id', 'user_id'])]
#[ORM\Index(name: 'IDX_KADHIA_MEMBERS_KADHIA', columns: ['kadhia_id'])]
#[ORM\Index(name: 'IDX_KADHIA_MEMBERS_USER', columns: ['user_id'])]
class KadhiaMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Kadhia::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Kadhia $kadhia;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16)]
    private string $role = 'editor';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKadhia(): Kadhia
    {
        return $this->kadhia;
    }

    public function setKadhia(Kadhia $kadhia): static
    {
        $this->kadhia = $kadhia;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
