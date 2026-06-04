<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminStoreActivationChecklistStepOutput
{
    public function __construct(
        #[Groups(['admin_store_activation_checklist:read', 'admin_store_list:read'])]
        public string $key,
        #[Groups(['admin_store_activation_checklist:read', 'admin_store_list:read'])]
        public string $label,
        #[Groups(['admin_store_activation_checklist:read', 'admin_store_list:read'])]
        public bool $completed,
        #[Groups(['admin_store_activation_checklist:read', 'admin_store_list:read'])]
        public bool $required,
        #[Groups(['admin_store_activation_checklist:read', 'admin_store_list:read'])]
        #[SerializedName('current_value')]
        public ?int $currentValue = null,
        #[Groups(['admin_store_activation_checklist:read', 'admin_store_list:read'])]
        #[SerializedName('target_value')]
        public ?int $targetValue = null,
    ) {
    }
}
