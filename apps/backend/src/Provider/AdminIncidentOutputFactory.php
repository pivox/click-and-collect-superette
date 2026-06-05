<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\AdminIncidentHistoryEntryOutput;
use App\ApiResource\AdminIncidentNoteOutput;
use App\ApiResource\AdminIncidentOutput;
use App\Entity\Incident;
use App\Repository\IncidentNoteRepository;

final readonly class AdminIncidentOutputFactory
{
    public function __construct(
        private IncidentNoteRepository $incidentNoteRepository,
    ) {
    }

    public function fromIncident(Incident $incident): AdminIncidentOutput
    {
        $notes = array_map(
            static fn ($note) => new AdminIncidentNoteOutput(
                id: $note->getId()->toRfc4122(),
                note: $note->getNote(),
                createdByAdminId: $note->getCreatedByAdmin()->getId()->toRfc4122(),
                createdByAdminEmail: $note->getCreatedByAdmin()->getEmail(),
                createdAt: $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ),
            $this->incidentNoteRepository->findByIncidentOrdered($incident),
        );

        return new AdminIncidentOutput(
            id: $incident->getId()->toRfc4122(),
            orderId: $incident->getOrder()->getId()->toRfc4122(),
            merchantId: $incident->getMerchant()->getId()->toRfc4122(),
            merchantEmail: $incident->getMerchant()->getEmail(),
            customerId: $incident->getCustomer()->getId()->toRfc4122(),
            customerEmail: $incident->getCustomer()->getEmail(),
            type: $incident->getType()->value,
            status: $incident->getStatus()->value,
            occurredAt: $incident->getOccurredAt()->format(\DateTimeInterface::ATOM),
            createdAt: $incident->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $incident->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            closedAt: $incident->getClosedAt()?->format(\DateTimeInterface::ATOM),
            notes: $notes,
            history: $this->buildHistory($incident, $notes),
        );
    }

    /**
     * @param list<AdminIncidentNoteOutput> $notes
     *
     * @return list<AdminIncidentHistoryEntryOutput>
     */
    private function buildHistory(Incident $incident, array $notes): array
    {
        $history = [
            new AdminIncidentHistoryEntryOutput(
                event: 'incident.opened',
                occurredAt: $incident->getCreatedAt()->format(\DateTimeInterface::ATOM),
                summary: 'Incident créé.',
            ),
        ];

        foreach ($notes as $note) {
            $history[] = new AdminIncidentHistoryEntryOutput(
                event: 'incident.note_added',
                occurredAt: $note->createdAt,
                summary: $note->note,
            );
        }

        if (null !== $incident->getProcessingStartedAt()) {
            $history[] = new AdminIncidentHistoryEntryOutput(
                event: 'incident.processing_started',
                occurredAt: $incident->getProcessingStartedAt()->format(\DateTimeInterface::ATOM),
                summary: 'Incident pris en charge.',
            );
        }

        if (null !== $incident->getClosedAt()) {
            $history[] = new AdminIncidentHistoryEntryOutput(
                event: 'incident.closed',
                occurredAt: $incident->getClosedAt()->format(\DateTimeInterface::ATOM),
                summary: 'Incident clôturé.',
            );
        }

        usort(
            $history,
            static fn (AdminIncidentHistoryEntryOutput $left, AdminIncidentHistoryEntryOutput $right): int => self::historyTimestamp($left) <=> self::historyTimestamp($right),
        );

        return $history;
    }

    private static function historyTimestamp(AdminIncidentHistoryEntryOutput $entry): int
    {
        return (new \DateTimeImmutable($entry->occurredAt))->getTimestamp();
    }
}
