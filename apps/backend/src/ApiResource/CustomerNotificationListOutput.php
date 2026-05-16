<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use App\Processor\CustomerNotificationMarkAllReadProcessor;
use App\Provider\CustomerNotificationCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/notifications',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['customer_notification_list:read']],
            provider: CustomerNotificationCollectionProvider::class,
            security: "is_granted('ROLE_CUSTOMER')",
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'default' => 1],
                    description: 'Numéro de page (défaut : 1).',
                ),
                'unread' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'Si true, retourne uniquement les notifications non lues.',
                ),
            ],
        ),
        new Patch(
            uriTemplate: '/me/notifications/read-all',
            formats: ['json' => ['application/json']],
            status: 204,
            output: false,
            read: false,
            input: false,
            processor: CustomerNotificationMarkAllReadProcessor::class,
            security: "is_granted('ROLE_CUSTOMER')",
        ),
    ],
)]
final readonly class CustomerNotificationListOutput
{
    /**
     * @param list<CustomerNotificationItemOutput> $items
     */
    public function __construct(
        // Virtual identifier: the authenticated user's UUID. Not exposed in the URL.
        public string $id,
        #[Groups(['customer_notification_list:read'])]
        public array $items,
        #[Groups(['customer_notification_list:read'])]
        public int $total,
        #[Groups(['customer_notification_list:read'])]
        public int $page,
    ) {
    }
}
