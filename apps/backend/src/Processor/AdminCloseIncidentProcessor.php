<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminIncidentOutput;
use App\Provider\AdminIncidentItemProvider;
use App\Provider\AdminIncidentOutputFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<mixed, AdminIncidentOutput>
 */
final readonly class AdminCloseIncidentProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminIncidentItemProvider $incidentProvider,
        private AdminIncidentOutputFactory $outputFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminIncidentOutput
    {
        $incident = $this->incidentProvider->resolveIncident((string) ($uriVariables['incidentId'] ?? ''));
        try {
            $incident->close(new \DateTimeImmutable());
        } catch (\LogicException $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }
        $this->entityManager->flush();

        return $this->outputFactory->fromIncident($incident);
    }
}
