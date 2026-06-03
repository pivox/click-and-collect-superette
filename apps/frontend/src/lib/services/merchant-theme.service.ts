import { apiClient } from '@/lib/api';
import { MOCK_MERCHANT_THEME, type MerchantTheme } from '@/lib/mock/merchant-theme.mock';
import { USE_MOCKS, mockDelay } from './index';

export type { MerchantTheme };

interface ApiMerchantTheme {
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  text_color: string;
  background_color: string;
  font_family: string;
  base_font_size: number;
  warnings: { code: string; message: string; contrast_ratio: number }[];
}

function mapApi(data: ApiMerchantTheme): MerchantTheme {
  return {
    primaryColor: data.primary_color,
    secondaryColor: data.secondary_color,
    accentColor: data.accent_color,
    textColor: data.text_color,
    backgroundColor: data.background_color,
    fontFamily: data.font_family,
    baseFontSize: data.base_font_size,
    warnings: (data.warnings ?? []).map((w) => ({
      code: w.code,
      message: w.message,
      contrastRatio: w.contrast_ratio,
    })),
  };
}

let mockTheme: MerchantTheme = { ...MOCK_MERCHANT_THEME };

export async function getMerchantTheme(storeId: string): Promise<MerchantTheme> {
  if (USE_MOCKS) return mockDelay({ ...mockTheme });
  const { data } = await apiClient.get<ApiMerchantTheme>(
    `/api/merchant/stores/${storeId}/theme`,
  );
  return mapApi(data);
}

export async function putMerchantTheme(
  storeId: string,
  theme: Omit<MerchantTheme, 'warnings'>,
): Promise<MerchantTheme> {
  if (USE_MOCKS) {
    mockTheme = { ...theme, warnings: [] };
    return mockDelay({ ...mockTheme });
  }
  const { data } = await apiClient.put<ApiMerchantTheme>(
    `/api/merchant/stores/${storeId}/theme`,
    {
      primary_color: theme.primaryColor,
      secondary_color: theme.secondaryColor,
      accent_color: theme.accentColor,
      text_color: theme.textColor,
      background_color: theme.backgroundColor,
      font_family: theme.fontFamily,
      base_font_size: theme.baseFontSize,
    },
  );
  return mapApi(data);
}
