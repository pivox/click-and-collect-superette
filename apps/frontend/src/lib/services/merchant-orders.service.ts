import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';
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

export async function exportOrdersCsv(
  storeId: string,
  params: { dateFrom: string; dateTo: string },
): Promise<Blob> {
  if (USE_MOCKS) {
    const bom = '﻿';
    const csv = [
      'Code;Statut;Client;Total TND;Créneau;Créé le',
      `CMD-4821;ready;client@test.com;10.550;${params.dateFrom} 18:00;${params.dateFrom}`,
      `CMD-SUBM-1;cancelled;client@test.com;7.700;${params.dateTo} 09:00;${params.dateTo}`,
    ].join('\n');
    return mockDelay(new Blob([bom + csv], { type: 'text/csv;charset=utf-8' }));
  }
  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
  const url = `${apiUrl}/api/merchant/stores/${storeId}/orders/history?format=csv&date_from=${params.dateFrom}&date_to=${params.dateTo}`;
  const token = typeof window !== 'undefined' ? localStorage.getItem('merchant_token') ?? '' : '';
  const response = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
  if (!response.ok) throw new Error('Export CSV failed');
  return response.blob();
}

export function triggerCsvDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
