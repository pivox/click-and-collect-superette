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
            metadata: $metadata,
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
        );

        $this->entityManager->persist($log);
    }
}
