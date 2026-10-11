import { apiClient } from '@/lib/api';
import type {
  CreateSubscriptionPaymentPayload,
  SubscriptionPayment,
  SubscriptionPaymentListResponse,
} from '@/lib/types/subscription-payments.types';

interface ListSubscriptionPaymentsOptions {
  subscription_id?: string;
  billing_document_id?: string;
  q?: string;
  page?: number;
  limit?: number;
}

export async function createAdminSubscriptionPayment(
  payload: CreateSubscriptionPaymentPayload,
): Promise<SubscriptionPayment> {
  const { data } = await apiClient.post<SubscriptionPayment>('/api/admin/subscription-payments', payload);

  return data;
}

export async function listAdminSubscriptionPayments(
  options: ListSubscriptionPaymentsOptions = {},
): Promise<SubscriptionPaymentListResponse> {
  const { data } = await apiClient.get<SubscriptionPaymentListResponse>('/api/admin/subscription-payments', {
    params: {
      page: options.page ?? 1,
      limit: options.limit ?? 20,
      ...(options.subscription_id !== undefined ? { subscription_id: options.subscription_id } : {}),
      ...(options.billing_document_id !== undefined ? { billing_document_id: options.billing_document_id } : {}),
      ...(options.q !== undefined ? { q: options.q } : {}),
    },
  });

  return data;
}

export async function listMerchantSubscriptionPayments(
  options: ListSubscriptionPaymentsOptions = {},
): Promise<SubscriptionPaymentListResponse> {
  const { data } = await apiClient.get<SubscriptionPaymentListResponse>('/api/merchant/subscription-payments', {
    params: {
      page: options.page ?? 1,
      limit: options.limit ?? 20,
    },
  });

  return data;
}
