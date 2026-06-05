<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminIncidentListOutput;
use App\Enum\IncidentStatus;
use App\Repository\IncidentRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminIncidentListOutput>
 */
final readonly class AdminIncidentCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private IncidentRepository $incidentRepository,
        private UserRepository $userRepository,
        private AdminIncidentOutputFactory $outputFactory,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminIncidentListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_INCIDENT_INVALID_PAGE');
        $limit = min(self::MAX_LIMIT, $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_INCIDENT_INVALID_LIMIT'));
        $merchant = $this->resolveUserFilter($request?->query->get('merchant'), 'ADMIN_INCIDENT_INVALID_MERCHANT');
        $customer = $this->resolveUserFilter($request?->query->get('client'), 'ADMIN_INCIDENT_INVALID_CLIENT');
        $status = $this->parseStatus($request?->query->get('status'));
        $offset = ($page - 1) * $limit;

        $items = array_map(
            fn ($incident) => $this->outputFactory->fromIncident($incident),
            $this->incidentRepository->findAdminPaginated($limit, $offset, $merchant, $customer, $status),
        );

        return new AdminIncidentListOutput(
            id: 'admin-incidents',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->incidentRepository->countAdmin($merchant, $customer, $status),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }
        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }
        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }

    private function resolveUserFilter(mixed $raw, string $errorCode): ?\App\Entity\User
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $value = (string) $raw;
        if (!Uuid::isValid($value)) {
            throw new BadRequestHttpException($errorCode);
        }

        $user = $this->userRepository->find($value);
        if (null === $user) {
            throw new BadRequestHttpException($errorCode);
        }

        return $user;
    }

    private function parseStatus(mixed $raw): ?IncidentStatus
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $status = IncidentStatus::tryFrom((string) $raw);
        if (null === $status) {
            throw new BadRequestHttpException('ADMIN_INCIDENT_INVALID_STATUS');
        }

        return $status;
    }
}
