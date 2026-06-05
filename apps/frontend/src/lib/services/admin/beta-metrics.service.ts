import { apiClient } from '@/lib/api';
import type {
  AdminBetaMetrics,
  AdminBetaMetricsFilters,
} from '@/lib/types/admin/beta-metrics.types';

export async function getAdminBetaMetrics(
  filters: AdminBetaMetricsFilters = {},
): Promise<AdminBetaMetrics> {
  const params: Record<string, string> = {};
  if (filters.dateFrom) {
    params.date_from = filters.dateFrom;
  }
  if (filters.dateTo) {
    params.date_to = filters.dateTo;
  }
  if (filters.storeId) {
    params.store_id = filters.storeId;
  }

  const { data } = await apiClient.get<AdminBetaMetrics>('/api/admin/beta-metrics', {
    params,
  });
  return data;
}
