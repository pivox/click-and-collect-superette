export type AdminPromotionStatusFilter = 'active' | 'expired';

export interface AdminPromotionItem {
  id: string;
  product_name: string;
  store_id: string;
  store_name: string;
  merchant_id: string | null;
  merchant_email: string | null;
  price_tnd: string;
  promotion_price_tnd: string;
  promotion_ends_on: string;
  promotion_active: boolean;
  is_available: boolean;
  is_visible: boolean;
}

export interface AdminPromotionListResponse {
  items: AdminPromotionItem[];
  page: number;
  limit: number;
  total: number;
}
