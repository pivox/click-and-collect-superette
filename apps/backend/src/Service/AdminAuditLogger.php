<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AdminAuditLogger
{
    private const int USER_AGENT_MAX_LENGTH = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Persists an audit log entry without flushing.
     * The caller's flush() commits the log atomically with the business operation.
     *
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        string $action,
        string $resourceType,
        string $resourceId,
        ?string $summary = null,
        ?array $metadata = null,
    ): void {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('AdminAuditLogger::log() called without an authenticated User.');
        }

        $request = $this->requestStack->getCurrentRequest();

        $log = new AdminAuditLog(
            adminUser: $user,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            summary: $summary,
            metadata: $metadata,
            ipAddress: $request?->getClientIp(),
            userAgent: $this->truncateUserAgent($request?->headers->get('User-Agent')),
        );

        $this->entityManager->persist($log);
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if (null === $userAgent) {
            return null;
        }

        return mb_substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH);
    }
}
