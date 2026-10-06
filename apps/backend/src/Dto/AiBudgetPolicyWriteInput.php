<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AiBudgetPolicyWriteInput
{
    public function __construct(
        #[Assert\NotNull]
        public bool $enabled,
        #[Assert\NotNull]
        #[SerializedName('openai_enabled')]
        public bool $openaiEnabled,
        #[Assert\NotNull]
        #[SerializedName('gemini_enabled')]
        public bool $geminiEnabled,
        #[Assert\NotNull]
        #[SerializedName('mistral_enabled')]
        public bool $mistralEnabled,
        #[Assert\NotNull]
        #[SerializedName('qwen_enabled')]
        public bool $qwenEnabled,
        #[Assert\NotBlank]
        #[Assert\Regex('/^[A-Z]{3}$/')]
        public string $currency,
        #[Assert\Range(min: 1, max: 365)]
        #[SerializedName('global_period_days')]
        public int $globalPeriodDays,
        #[Assert\Range(min: 1, max: 99)]
        #[SerializedName('alert_threshold_percent')]
        public int $alertThresholdPercent,
        #[Assert\Regex('/^\d{1,8}(\.\d{1,4})?$/')]
        #[SerializedName('max_cost_per_call_amount')]
        public ?string $maxCostPerCallAmount = null,
        #[Assert\Regex('/^\d{1,8}(\.\d{1,4})?$/')]
        #[SerializedName('max_cost_per_photo_amount')]
        public ?string $maxCostPerPhotoAmount = null,
        #[Assert\Regex('/^\d{1,8}(\.\d{1,4})?$/')]
        #[SerializedName('max_cost_per_shop_amount')]
        public ?string $maxCostPerShopAmount = null,
        #[Assert\Regex('/^\d{1,8}(\.\d{1,4})?$/')]
        #[SerializedName('global_period_cap_amount')]
        public ?string $globalPeriodCapAmount = null,
        #[Assert\PositiveOrZero]
        #[SerializedName('max_output_tokens_per_call')]
        public ?int $maxOutputTokensPerCall = null,
        #[Assert\PositiveOrZero]
        #[SerializedName('max_crops_per_photo')]
        public ?int $maxCropsPerPhoto = null,
        #[Assert\PositiveOrZero]
        #[SerializedName('max_attempts_per_photo')]
        public ?int $maxAttemptsPerPhoto = null,
        #[Assert\PositiveOrZero]
        #[SerializedName('max_call_duration_seconds')]
        public ?int $maxCallDurationSeconds = null,
        #[Assert\PositiveOrZero]
        #[SerializedName('max_concurrent_calls')]
        public ?int $maxConcurrentCalls = null,
    ) {
    }
}
