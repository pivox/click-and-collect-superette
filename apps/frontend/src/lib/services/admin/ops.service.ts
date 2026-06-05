import { apiClient } from '@/lib/api';
import type { AdminMessengerMonitoring } from '@/lib/types/admin/ops.types';

export async function getAdminMessengerMonitoring(): Promise<AdminMessengerMonitoring> {
  const { data } = await apiClient.get<AdminMessengerMonitoring>('/api/admin/ops/messenger');

  return data;
}
