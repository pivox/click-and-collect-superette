<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminIncidentHistoryEntryOutput
{
    public function __construct(
        #[Groups(['admin_incident:read'])]
        public string $event,
        #[Groups(['admin_incident:read'])]
        #[SerializedName('occurred_at')]
        public string $occurredAt,
        #[Groups(['admin_incident:read'])]
        public ?string $summary = null,
    ) {
    }
}
