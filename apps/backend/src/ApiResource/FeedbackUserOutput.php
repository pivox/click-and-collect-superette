<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class FeedbackUserOutput
{
    public function __construct(
        #[Groups(['feedback:read', 'admin_feedback:read', 'admin_feedback_list:read'])]
        public string $id,
        #[Groups(['admin_feedback:read', 'admin_feedback_list:read'])]
        public string $email,
        #[Groups(['admin_feedback:read', 'admin_feedback_list:read'])]
        public string $name,
    ) {
    }
}
