import { apiClient } from '@/lib/api';

export interface MerchantQrCode {
  store_name: string;
  slug: string;
  target_url: string;
  qr_code_token: string;
}

export async function getMerchantQrCode(storeId: string): Promise<MerchantQrCode> {
  const { data } = await apiClient.get<MerchantQrCode>(
    `/api/merchant/stores/${storeId}/qr-code`,
  );
  return data;
}

export async function downloadMerchantQrAsset(
  storeId: string,
  format: 'png' | 'pdf',
): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(
    `/api/merchant/stores/${storeId}/qr-code.${format}`,
    { responseType: 'blob' },
  );

  return data;
}

export function triggerQrDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
