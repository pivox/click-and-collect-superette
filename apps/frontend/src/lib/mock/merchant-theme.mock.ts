export interface MerchantTheme {
  primaryColor: string;
  secondaryColor: string;
  accentColor: string;
  textColor: string;
  backgroundColor: string;
  fontFamily: string;
  baseFontSize: number;
  warnings: { code: string; message: string; contrastRatio: number }[];
}

export const MOCK_MERCHANT_THEME: MerchantTheme = {
  primaryColor: '#1f7a4d',
  secondaryColor: '#ffcf5a',
  accentColor: '#e63946',
  textColor: '#172018',
  backgroundColor: '#f6f7f2',
  fontFamily: 'inter',
  baseFontSize: 16,
  warnings: [],
};
