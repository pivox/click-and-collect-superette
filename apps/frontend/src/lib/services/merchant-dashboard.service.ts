import { apiClient } from '@/lib/api';
import type { MerchantDashboardToday } from '@/lib/types/merchant.types';

export async function getMerchantDashboardToday(
  storeId: string,
): Promise<MerchantDashboardToday> {
  const { data } = await apiClient.get<MerchantDashboardToday>(
    `/api/merchant/stores/${storeId}/dashboard/today`,
  );
  return data;
}
