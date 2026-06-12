import { apiClient } from '@/lib/api';
import type {
  AdminPromotionListResponse,
  AdminPromotionStatusFilter,
} from '@/lib/types/admin/promotions.types';

export async function listPromotions(
  page = 1,
  limit = 20,
  status?: AdminPromotionStatusFilter,
  storeId?: string,
  merchantId?: string,
): Promise<AdminPromotionListResponse> {
  const { data } = await apiClient.get<AdminPromotionListResponse>('/api/admin/promotions', {
    params: {
      page,
      limit,
      ...(status ? { promotion_status: status } : {}),
      ...(storeId ? { store_id: storeId } : {}),
      ...(merchantId ? { merchant_id: merchantId } : {}),
    },
  });
  return data;
}
