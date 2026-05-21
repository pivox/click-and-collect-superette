<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminAuditLogItemOutput;
use App\ApiResource\AdminAuditLogListOutput;
use App\Entity\AdminAuditLog;
use App\Repository\AdminAuditLogRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminAuditLogListOutput>
 */
final readonly class AdminAuditLogCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminAuditLogRepository $adminAuditLogRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminAuditLogListOutput
    {
        $request = $this->requestStack->getCurrentRequest();

        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_AUDIT_LOG_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_AUDIT_LOG_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $action = $request?->query->get('action') ?: null;
        $resourceType = $request?->query->get('resource_type') ?: null;
        $resourceId = $request?->query->get('resource_id') ?: null;
        $adminId = $request?->query->get('admin') ?: null;
        if (null !== $adminId && !Uuid::isValid($adminId)) {
            throw new BadRequestHttpException('ADMIN_AUDIT_LOG_INVALID_ADMIN');
        }

        $logs = $this->adminAuditLogRepository->findPaginated($limit, $offset, $action, $resourceType, $resourceId, $adminId);
        $items = array_map(static fn (AdminAuditLog $log) => self::toItemOutput($log), $logs);

        return new AdminAuditLogListOutput(
            id: 'admin-audit-logs',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminAuditLogRepository->countAll($action, $resourceType, $resourceId, $adminId),
        );
    }

    private static function toItemOutput(AdminAuditLog $log): AdminAuditLogItemOutput
    {
        return new AdminAuditLogItemOutput(
            id: $log->getId()->toRfc4122(),
            action: $log->getAction(),
            resourceType: $log->getResourceType(),
            resourceId: $log->getResourceId(),
            summary: $log->getSummary(),
            metadata: $log->getMetadata(),
            adminId: $log->getAdminUser()->getId()->toRfc4122(),
            adminEmail: $log->getAdminUser()->getEmail(),
            ipAddress: $log->getIpAddress(),
            userAgent: $log->getUserAgent(),
            createdAt: $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
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
}
