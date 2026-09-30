<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * One merchant organization row in the admin collection (MERCHANT-TEAM-002).
 */
final readonly class AdminMerchantOrganizationOutput
{
    /**
     * @param array{user_id: string, email: string, name: string}|null $primaryAccount
     */
    public function __construct(
        #[Groups(['admin_merchant_organization:read'])]
        public string $id,
        #[Groups(['admin_merchant_organization:read'])]
        public string $name,
        #[Groups(['admin_merchant_organization:read'])]
        public bool $active,
        #[Groups(['admin_merchant_organization:read'])]
        #[SerializedName('primary_account')]
        public ?array $primaryAccount,
        #[Groups(['admin_merchant_organization:read'])]
        #[SerializedName('accounts_count')]
        public int $accountsCount,
        #[Groups(['admin_merchant_organization:read'])]
        #[SerializedName('stores_count')]
        public int $storesCount,
        #[Groups(['admin_merchant_organization:read'])]
        #[SerializedName('subscription_status')]
        public ?string $subscriptionStatus,
    ) {
    }
}
