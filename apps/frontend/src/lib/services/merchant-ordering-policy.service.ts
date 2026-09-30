import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

// ORDER-LEAD-003: per-store ordering policy (minimum pickup lead time).

export interface MerchantOrderingPolicy {
  storeId: string;
  minimumPickupLeadTimeMinutes: number;
  updatedAt: string | null;
}

interface ApiMerchantOrderingPolicy {
  store_id: string;
  minimum_pickup_lead_time_minutes: number;
  updated_at?: string | null;
}

function mapApi(data: ApiMerchantOrderingPolicy): MerchantOrderingPolicy {
  return {
    storeId: data.store_id,
    minimumPickupLeadTimeMinutes: data.minimum_pickup_lead_time_minutes,
    updatedAt: data.updated_at ?? null,
  };
}

let mockPolicy: MerchantOrderingPolicy = {
  storeId: 'store-1',
  minimumPickupLeadTimeMinutes: 0,
  updatedAt: null,
};

export async function getMerchantOrderingPolicy(storeId: string): Promise<MerchantOrderingPolicy> {
  if (USE_MOCKS) return mockDelay({ ...mockPolicy });
  const { data } = await apiClient.get<ApiMerchantOrderingPolicy>(
    `/api/merchant/stores/${storeId}/ordering-policy`,
  );
  return mapApi(data);
}

export async function updateMerchantOrderingPolicy(
  storeId: string,
  minimumPickupLeadTimeMinutes: number,
): Promise<MerchantOrderingPolicy> {
  if (USE_MOCKS) {
    mockPolicy = {
      ...mockPolicy,
      minimumPickupLeadTimeMinutes,
      updatedAt: new Date().toISOString(),
    };
    return mockDelay({ ...mockPolicy });
  }
  const { data } = await apiClient.patch<ApiMerchantOrderingPolicy>(
    `/api/merchant/stores/${storeId}/ordering-policy`,
    { minimum_pickup_lead_time_minutes: minimumPickupLeadTimeMinutes },
  );
  return mapApi(data);
}
