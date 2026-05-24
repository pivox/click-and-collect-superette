import { apiClient } from '@/lib/api';
import type {
  MerchantPickupSessionActionResult,
  MerchantPickupSessionForceCompleteResult,
  MerchantPickupSessionScanResult,
} from '@/lib/types/merchant.types';

export async function scanMerchantPickupSession(
  token: string,
): Promise<MerchantPickupSessionScanResult> {
  const { data } = await apiClient.post<MerchantPickupSessionScanResult>(
    '/api/merchant/pickup-sessions/scan',
    { token },
  );
  return data;
}

export async function confirmMerchantPickupSession(
  sessionId: string,
): Promise<MerchantPickupSessionActionResult> {
  const { data } = await apiClient.patch<MerchantPickupSessionActionResult>(
    `/api/merchant/pickup-sessions/${sessionId}/confirm`,
    {},
  );
  return data;
}

export async function forceCompleteMerchantPickupSession(
  sessionId: string,
  note: string,
): Promise<MerchantPickupSessionForceCompleteResult> {
  const { data } = await apiClient.patch<MerchantPickupSessionForceCompleteResult>(
    `/api/merchant/pickup-sessions/${sessionId}/force-complete`,
    { note },
  );
  return data;
}
