<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Dto\AiBudgetPolicyWriteInput;
use App\Processor\UpdateAiBudgetPolicyProcessor;
use App\Provider\AiBudgetPolicyProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/ai-budget-policy',
            formats: ['json' => ['application/json']],
            provider: AiBudgetPolicyProvider::class,
            normalizationContext: ['groups' => ['admin_ai_budget_policy:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Put(
            uriTemplate: '/admin/ai-budget-policy',
            formats: ['json' => ['application/json']],
            input: AiBudgetPolicyWriteInput::class,
            output: self::class,
            read: false,
            processor: UpdateAiBudgetPolicyProcessor::class,
            normalizationContext: ['groups' => ['admin_ai_budget_policy:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
)]
final readonly class AiBudgetPolicyOutput
{
    public function __construct(
        #[Groups(['admin_ai_budget_policy:read'])]
        public bool $enabled,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('openai_enabled')]
        public bool $openaiEnabled,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('gemini_enabled')]
        public bool $geminiEnabled,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('mistral_enabled')]
        public bool $mistralEnabled,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('qwen_enabled')]
        public bool $qwenEnabled,
        #[Groups(['admin_ai_budget_policy:read'])]
        public string $currency,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('global_period_days')]
        public int $globalPeriodDays,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('alert_threshold_percent')]
        public int $alertThresholdPercent,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_cost_per_call_amount')]
        public ?string $maxCostPerCallAmount,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_cost_per_photo_amount')]
        public ?string $maxCostPerPhotoAmount,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_cost_per_shop_amount')]
        public ?string $maxCostPerShopAmount,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('global_period_cap_amount')]
        public ?string $globalPeriodCapAmount,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_output_tokens_per_call')]
        public ?int $maxOutputTokensPerCall,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_crops_per_photo')]
        public ?int $maxCropsPerPhoto,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_attempts_per_photo')]
        public ?int $maxAttemptsPerPhoto,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_call_duration_seconds')]
        public ?int $maxCallDurationSeconds,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('max_concurrent_calls')]
        public ?int $maxConcurrentCalls,
        #[Groups(['admin_ai_budget_policy:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
    ) {
    }
}
