<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * PATCH input for the merchant shop ordering policy.
 *
 * The value is deliberately typed `mixed`: strict validation (integer only,
 * 0..10080, field required, unknown fields rejected) happens in the processor
 * from the raw payload so every invalid shape maps to a stable 422 code
 * instead of a serializer-dependent denormalization error.
 */
final class ShopOrderingPolicyPatchInput
{
    #[SerializedName('minimum_pickup_lead_time_minutes')]
    public mixed $minimumPickupLeadTimeMinutes = null;
}
