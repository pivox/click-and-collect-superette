import { apiClient } from '@/lib/api';
import type {
  AdminAiBudgetPolicy,
  AdminAiBudgetPolicyPayload,
} from '@/lib/types/admin/aiBudgetPolicy.types';

type NullableCapField =
  | 'max_cost_per_call_amount'
  | 'max_cost_per_photo_amount'
  | 'max_cost_per_shop_amount'
  | 'global_period_cap_amount'
  | 'max_output_tokens_per_call'
  | 'max_crops_per_photo'
  | 'max_attempts_per_photo'
  | 'max_call_duration_seconds'
  | 'max_concurrent_calls';

const NULLABLE_CAP_FIELDS: NullableCapField[] = [
  'max_cost_per_call_amount',
  'max_cost_per_photo_amount',
  'max_cost_per_shop_amount',
  'global_period_cap_amount',
  'max_output_tokens_per_call',
  'max_crops_per_photo',
  'max_attempts_per_photo',
  'max_call_duration_seconds',
  'max_concurrent_calls',
];

// API Platform omits nullable fields from the serialized response rather than
// sending them as null — normalize so the frontend can rely on the key always existing.
function normalize(data: Partial<AdminAiBudgetPolicy>): AdminAiBudgetPolicy {
  const normalized = { ...data } as AdminAiBudgetPolicy;
  for (const field of NULLABLE_CAP_FIELDS) {
    (normalized as Record<NullableCapField, string | number | null>)[field] ??= null;
  }

  return normalized;
}

export async function getAdminAiBudgetPolicy(): Promise<AdminAiBudgetPolicy> {
  const { data } = await apiClient.get<Partial<AdminAiBudgetPolicy>>('/api/admin/ai-budget-policy');

  return normalize(data);
}

export async function updateAdminAiBudgetPolicy(
  payload: AdminAiBudgetPolicyPayload,
): Promise<AdminAiBudgetPolicy> {
  const { data } = await apiClient.put<Partial<AdminAiBudgetPolicy>>('/api/admin/ai-budget-policy', payload);

  return normalize(data);
}
