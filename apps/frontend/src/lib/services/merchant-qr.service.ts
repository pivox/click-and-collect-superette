import { apiClient } from '@/lib/api';

export interface MerchantQrCode {
  target_url: string;
  qr_code_token: string;
}

export async function getMerchantQrCode(storeId: string): Promise<MerchantQrCode> {
  const { data } = await apiClient.get<MerchantQrCode>(
    `/api/merchant/stores/${storeId}/qr-code`,
  );
  return data;
}
