<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use App\Processor\MerchantNotificationMarkAllReadProcessor;
use App\Provider\MerchantNotificationCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/notifications',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_notification_list:read']],
            provider: MerchantNotificationCollectionProvider::class,
            security: "is_granted('ROLE_MERCHANT')",
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
            uriTemplate: '/merchant/notifications/read-all',
            formats: ['json' => ['application/json']],
            status: 204,
            output: false,
            read: false,
            input: false,
            processor: MerchantNotificationMarkAllReadProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantNotificationListOutput
{
    /**
     * @param list<MerchantNotificationItemOutput> $items
     */
    public function __construct(
        // Virtual identifier: the authenticated merchant's UUID. Not exposed in the URL.
        public string $id,
        #[Groups(['merchant_notification_list:read'])]
        public array $items,
        #[Groups(['merchant_notification_list:read'])]
        public int $total,
        #[Groups(['merchant_notification_list:read'])]
        public int $page,
    ) {
    }
}
