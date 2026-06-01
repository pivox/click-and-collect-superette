<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthCheckController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/api/health', name: 'api_health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseStatus = $this->checkDatabase();
        $isHealthy = 'ok' === $databaseStatus;

        return new JsonResponse([
            'status' => $isHealthy ? 'ok' : 'error',
            'checks' => [
                'database' => $databaseStatus,
            ],
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $isHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function checkDatabase(): string
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1')->fetchOne();

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }
}
