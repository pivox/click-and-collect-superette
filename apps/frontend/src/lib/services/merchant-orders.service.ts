import { apiClient } from '@/lib/api';
import type {
  MerchantOrderDetail,
  MerchantOrderHistoryList,
  MerchantOrderList,
  MerchantOrderMutationResult,
  PartiallyAcceptMerchantOrderPayload,
  RejectMerchantOrderPayload,
  SetMerchantOrderLinePreparedPayload,
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

export async function getMerchantOrder(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderDetail> {
  const { data } = await apiClient.get<MerchantOrderDetail>(
    `/api/merchant/stores/${storeId}/orders/${orderId}`,
  );
  return data;
}

export async function acceptMerchantOrder(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/accept`,
  );
  return data;
}

export async function rejectMerchantOrder(
  storeId: string,
  orderId: string,
  payload: RejectMerchantOrderPayload,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/reject`,
    payload,
  );
  return data;
}

export async function partiallyAcceptMerchantOrder(
  storeId: string,
  orderId: string,
  payload: PartiallyAcceptMerchantOrderPayload,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/partially-accept`,
    payload,
  );
  return data;
}

export async function startMerchantOrderPreparation(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/start-preparation`,
  );
  return data;
}

export async function setMerchantOrderLinePrepared(
  storeId: string,
  orderId: string,
  merchantProductId: string,
  payload: SetMerchantOrderLinePreparedPayload,
): Promise<MerchantOrderDetail> {
  const { data } = await apiClient.patch<MerchantOrderDetail>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/lines/${merchantProductId}/preparation`,
    payload,
  );
  return data;
}

export async function markMerchantOrderReady(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/mark-ready`,
  );
  return data;
}
