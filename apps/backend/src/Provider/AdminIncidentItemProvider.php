<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminIncidentOutput;
use App\Repository\IncidentRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminIncidentOutput>
 */
final readonly class AdminIncidentItemProvider implements ProviderInterface
{
    public function __construct(
        private IncidentRepository $incidentRepository,
        private AdminIncidentOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminIncidentOutput
    {
        return $this->outputFactory->fromIncident($this->resolveIncident((string) ($uriVariables['incidentId'] ?? '')));
    }

    public function resolveIncident(string $incidentId): \App\Entity\Incident
    {
        if (!Uuid::isValid($incidentId)) {
            throw new NotFoundHttpException('ADMIN_INCIDENT_NOT_FOUND');
        }
        $incident = $this->incidentRepository->find($incidentId);
        if (null === $incident) {
            throw new NotFoundHttpException('ADMIN_INCIDENT_NOT_FOUND');
        }

        return $incident;
    }
}
