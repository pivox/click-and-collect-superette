import { apiClient } from '@/lib/api';
import type {
  AdminSubscriptionListResponse,
  MerchantSubscription,
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
