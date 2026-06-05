<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminIncidentNoteOutput
{
    public function __construct(
        #[Groups(['admin_incident:read'])]
        public string $id,
        #[Groups(['admin_incident:read'])]
        public string $note,
        #[Groups(['admin_incident:read'])]
        #[SerializedName('created_by_admin_id')]
        public string $createdByAdminId,
        #[Groups(['admin_incident:read'])]
        #[SerializedName('created_by_admin_email')]
        public string $createdByAdminEmail,
        #[Groups(['admin_incident:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
