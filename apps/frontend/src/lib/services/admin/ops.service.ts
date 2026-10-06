import { apiClient } from '@/lib/api';
import type { AdminMessengerMonitoring } from '@/lib/types/admin/ops.types';

export async function getAdminMessengerMonitoring(): Promise<AdminMessengerMonitoring> {
  const { data } = await apiClient.get<Omit<AdminMessengerMonitoring, 'oldest_age_s' | 'last_consumed_at'> & {
    oldest_age_s?: number | null;
    last_consumed_at?: string | null;
  }>('/api/admin/ops/messenger');

  // API Platform may omit nullable fields from the serialized response.
  return {
    ...data,
    oldest_age_s: data.oldest_age_s ?? null,
    last_consumed_at: data.last_consumed_at ?? null,
  };
}
