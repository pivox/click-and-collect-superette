<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminIncidentOutput;
use App\Dto\AdminIncidentNoteCreateInput;
use App\Entity\IncidentNote;
use App\Entity\User;
use App\Provider\AdminIncidentItemProvider;
use App\Provider\AdminIncidentOutputFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<AdminIncidentNoteCreateInput, AdminIncidentOutput>
 */
final readonly class AdminAddIncidentNoteProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminIncidentItemProvider $incidentProvider,
        private AdminIncidentOutputFactory $outputFactory,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminIncidentOutput
    {
        if (!$data instanceof AdminIncidentNoteCreateInput) {
            throw new \InvalidArgumentException('AdminIncidentNoteCreateInput expected.');
        }
        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException('ADMIN_ACCESS_REQUIRED');
        }
        $note = trim((string) $data->note);
        if ('' === $note) {
            throw new UnprocessableEntityHttpException('ADMIN_INCIDENT_NOTE_REQUIRED');
        }

        $incident = $this->incidentProvider->resolveIncident((string) ($uriVariables['incidentId'] ?? ''));
        $incidentNote = new IncidentNote($incident, $admin, $note);
        $this->entityManager->persist($incidentNote);
        $this->entityManager->flush();

        return $this->outputFactory->fromIncident($incident);
    }
}
