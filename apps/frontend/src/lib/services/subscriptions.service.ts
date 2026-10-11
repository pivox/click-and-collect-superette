import { apiClient } from '@/lib/api';
import type { BillingDocument } from '@/lib/types/billing-documents.types';
import type {
  AdminSubscriptionListResponse,
  MerchantSubscription,
  SubscriptionPricingInput,
} from '@/lib/types/subscriptions.types';

interface ListAdminSubscriptionsOptions {
  page?: number;
  limit?: number;
}

export async function listAdminSubscriptions(
  options: ListAdminSubscriptionsOptions = {},
): Promise<AdminSubscriptionListResponse> {
  const { data } = await apiClient.get<AdminSubscriptionListResponse>('/api/admin/subscriptions', {
    params: {
      page: options.page ?? 1,
      limit: options.limit ?? 20,
    },
  });

  return data;
}

export async function getAdminSubscription(subscriptionId: string): Promise<MerchantSubscription> {
  const { data } = await apiClient.get<MerchantSubscription>(
    `/api/admin/subscriptions/${subscriptionId}`,
  );

  return data;
}

export async function getMerchantSubscription(): Promise<MerchantSubscription> {
  const { data } = await apiClient.get<MerchantSubscription>('/api/merchant/subscription');

  return data;
}

export async function createAdminSubscription(
  input: SubscriptionPricingInput & { merchant_id: string },
): Promise<MerchantSubscription> {
  const { data } = await apiClient.post<MerchantSubscription>('/api/admin/subscriptions', input);
  return data;
}

export async function previewNewAdminSubscription(
  input: SubscriptionPricingInput & { merchant_id: string },
): Promise<MerchantSubscription> {
  const { data } = await apiClient.post<MerchantSubscription>('/api/admin/subscriptions/pricing-preview', input);
  return data;
}

export async function previewAdminSubscriptionPricing(
  id: string, input: SubscriptionPricingInput,
): Promise<MerchantSubscription> {
  const { data } = await apiClient.post<MerchantSubscription>(`/api/admin/subscriptions/${id}/pricing-preview`, input);
  return data;
}

export async function updateAdminSubscriptionPricing(
  id: string, input: SubscriptionPricingInput,
): Promise<MerchantSubscription> {
  const { data } = await apiClient.put<MerchantSubscription>(`/api/admin/subscriptions/${id}/pricing`, input);
  return data;
}

export async function issueSubscriptionBillingDocument(id: string): Promise<BillingDocument> {
  const { data } = await apiClient.post<BillingDocument>(`/api/admin/subscriptions/${id}/billing-document`, {});
  return data;
}
