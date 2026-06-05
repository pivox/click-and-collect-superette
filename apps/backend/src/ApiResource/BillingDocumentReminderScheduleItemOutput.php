<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class BillingDocumentReminderScheduleItemOutput
{
    public function __construct(
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        public string $stage,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        public string $label,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('scheduled_at')]
        public string $scheduledAt,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('email_status')]
        public string $emailStatus,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('email_sent_at')]
        public ?string $emailSentAt,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('email_failed_at')]
        public ?string $emailFailedAt,
        #[Groups(['billing_document:read', 'billing_document_list:read'])]
        #[SerializedName('whatsapp_contacted_at')]
        public ?string $whatsappContactedAt,
    ) {
    }
}
