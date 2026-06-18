<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AdminCatalogPreloadOutput
{
    /**
     * @param list<AdminCatalogPreloadErrorOutput> $errors
     */
    public function __construct(
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('added_count')]
        public int $addedCount,
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('already_existing_count')]
        public int $alreadyExistingCount,
        #[Groups(['admin_merchant_onboarding:read'])]
        #[SerializedName('ignored_count')]
        public int $ignoredCount,
        #[Groups(['admin_merchant_onboarding:read'])]
        public array $errors,
    ) {
    }
}
