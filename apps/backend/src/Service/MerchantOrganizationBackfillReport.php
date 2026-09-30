<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Reconciliation report produced by the merchant organization backfill.
 */
final class MerchantOrganizationBackfillReport
{
    public int $merchantsSeen = 0;
    public int $organizationsCreated = 0;
    public int $membershipsCreated = 0;
    public int $shopsSeen = 0;
    public int $shopsAttached = 0;
    public int $orphanShops = 0;

    /** @var list<string> */
    public array $orphanShopIds = [];

    /**
     * @return array<string, int>
     */
    public function counters(): array
    {
        return [
            'merchants_seen' => $this->merchantsSeen,
            'organizations_created' => $this->organizationsCreated,
            'memberships_created' => $this->membershipsCreated,
            'shops_seen' => $this->shopsSeen,
            'shops_attached' => $this->shopsAttached,
            'orphan_shops' => $this->orphanShops,
        ];
    }
}
