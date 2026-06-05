<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IncidentNoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: IncidentNoteRepository::class)]
#[ORM\Table(name: 'incident_notes')]
#[ORM\Index(name: 'IDX_INCIDENT_NOTES_INCIDENT', columns: ['incident_id'])]
#[ORM\Index(name: 'IDX_INCIDENT_NOTES_ADMIN', columns: ['created_by_admin_id'])]
#[ORM\Index(name: 'IDX_INCIDENT_NOTES_CREATED_AT', columns: ['created_at'])]
class IncidentNote
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $createdByAdmin;

    #[ORM\Column(type: 'text')]
    private string $note;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Incident $incident, User $createdByAdmin, string $note, ?\DateTimeImmutable $createdAt = null)
    {
        $this->id = Uuid::v4();
        $this->incident = $incident;
        $this->createdByAdmin = $createdByAdmin;
        $this->note = trim($note);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getCreatedByAdmin(): User
    {
        return $this->createdByAdmin;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
