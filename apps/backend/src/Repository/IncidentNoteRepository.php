<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Incident;
use App\Entity\IncidentNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IncidentNote>
 */
class IncidentNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncidentNote::class);
    }

    /**
     * @return list<IncidentNote>
     */
    public function findByIncidentOrdered(Incident $incident): array
    {
        /* @var list<IncidentNote> */
        return $this->findBy(['incident' => $incident], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }
}
