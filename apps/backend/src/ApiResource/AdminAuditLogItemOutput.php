<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminAuditLogItemOutput
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        #[Groups(['admin_audit_log_list:read'])]
        public string $id,
        #[Groups(['admin_audit_log_list:read'])]
        public string $action,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('resource_type')]
        public string $resourceType,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('resource_id')]
        public string $resourceId,
        #[Groups(['admin_audit_log_list:read'])]
        public ?string $summary,
        #[Groups(['admin_audit_log_list:read'])]
        public ?array $metadata,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('admin_id')]
        public string $adminId,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('admin_email')]
        public string $adminEmail,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('ip_address')]
        public ?string $ipAddress,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('user_agent')]
        public ?string $userAgent,
        #[Groups(['admin_audit_log_list:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
