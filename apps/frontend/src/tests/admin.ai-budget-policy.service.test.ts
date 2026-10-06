import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getAdminAiBudgetPolicy,
  updateAdminAiBudgetPolicy,
} from '@/lib/services/admin/aiBudgetPolicy.service';

vi.mock('@/lib/api', () => ({ apiClient: { get: vi.fn(), put: vi.fn() } }));

describe('AI budget policy response', () => {
  beforeEach(() => vi.clearAllMocks());

  it('normalizes omitted nullable caps to null for the disabled default policy', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        enabled: false,
        openai_enabled: false,
        gemini_enabled: false,
        mistral_enabled: false,
        qwen_enabled: false,
        currency: 'USD',
        global_period_days: 30,
        alert_threshold_percent: 80,
        updated_at: '2026-10-06T18:00:00+01:00',
      },
    });

    const policy = await getAdminAiBudgetPolicy();

    expect(policy.max_cost_per_call_amount).toBeNull();
    expect(policy.global_period_cap_amount).toBeNull();
    expect(policy.max_concurrent_calls).toBeNull();
  });

  it('preserves configured caps after an update', async () => {
    vi.mocked(apiClient.put).mockResolvedValue({
      data: {
        enabled: true,
        openai_enabled: true,
        gemini_enabled: false,
        mistral_enabled: false,
        qwen_enabled: false,
        currency: 'USD',
        global_period_days: 30,
        alert_threshold_percent: 80,
        max_cost_per_call_amount: '0.0500',
        updated_at: '2026-10-06T18:05:00+01:00',
      },
    });

    const policy = await updateAdminAiBudgetPolicy({
      enabled: true,
      openai_enabled: true,
      gemini_enabled: false,
      mistral_enabled: false,
      qwen_enabled: false,
      currency: 'USD',
      global_period_days: 30,
      alert_threshold_percent: 80,
      max_cost_per_call_amount: '0.0500',
      max_cost_per_photo_amount: null,
      max_cost_per_shop_amount: null,
      global_period_cap_amount: null,
      max_output_tokens_per_call: null,
      max_crops_per_photo: null,
      max_attempts_per_photo: null,
      max_call_duration_seconds: null,
      max_concurrent_calls: null,
    });

    expect(policy.enabled).toBe(true);
    expect(policy.max_cost_per_call_amount).toBe('0.0500');
    expect(policy.max_cost_per_photo_amount).toBeNull();
  });
});
