export interface AdminAiBudgetPolicy {
  enabled: boolean;
  openai_enabled: boolean;
  gemini_enabled: boolean;
  mistral_enabled: boolean;
  qwen_enabled: boolean;
  currency: string;
  global_period_days: number;
  alert_threshold_percent: number;
  max_cost_per_call_amount: string | null;
  max_cost_per_photo_amount: string | null;
  max_cost_per_shop_amount: string | null;
  global_period_cap_amount: string | null;
  max_output_tokens_per_call: number | null;
  max_crops_per_photo: number | null;
  max_attempts_per_photo: number | null;
  max_call_duration_seconds: number | null;
  max_concurrent_calls: number | null;
  updated_at: string;
}

export type AdminAiBudgetPolicyPayload = Omit<AdminAiBudgetPolicy, 'updated_at'>;
