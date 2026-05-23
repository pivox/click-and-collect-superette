import { apiClient } from '@/lib/api';
import type {
  MerchantOrderHistoryList,
  MerchantOrderList,
} from '@/lib/types/merchant.types';

interface MerchantOrderListOptions {
  page?: number;
  limit?: number;
  status?: string;
}

export async function listMerchantOrders(
  storeId: string,
  options: MerchantOrderListOptions = {},
): Promise<MerchantOrderList> {
  const { data } = await apiClient.get<MerchantOrderList>(
    `/api/merchant/stores/${storeId}/orders`,
    {
      params: {
        page: options.page ?? 1,
        limit: options.limit ?? 20,
        ...(options.status ? { status: options.status } : {}),
      },
    },
  );
  return data;
}

export async function listMerchantOrderHistory(
  storeId: string,
  options: MerchantOrderListOptions = {},
): Promise<MerchantOrderHistoryList> {
  const { data } = await apiClient.get<MerchantOrderHistoryList>(
    `/api/merchant/stores/${storeId}/orders/history`,
    {
      params: {
        page: options.page ?? 1,
        limit: options.limit ?? 20,
        ...(options.status ? { status: options.status } : {}),
      },
    },
  );
  return data;
}
