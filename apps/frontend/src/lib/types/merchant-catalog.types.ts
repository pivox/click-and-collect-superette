export type MerchantCatalogAvailabilityFilter = 'all' | 'available' | 'unavailable';
export type MerchantCatalogVisibilityFilter = 'all' | 'visible' | 'hidden';

export interface MerchantCatalogProduct {
  id: string;
  product_reference_id: string | null;
  local_product_id?: string | null;
  name_fr: string;
  name_ar?: string | null;
  brand: string | null;
  category: string;
  merchant_category_id?: string | null;
  merchant_category_name?: string | null;
  volume: string | null;
  unit: string;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}

export interface MerchantCatalogListOptions {
  q?: string;
  availability?: MerchantCatalogAvailabilityFilter;
  visibility?: MerchantCatalogVisibilityFilter;
  category?: string;
}

export interface UpdateMerchantCatalogProductPayload {
  price_tnd?: string;
  is_available?: boolean;
  is_visible?: boolean;
  merchant_note?: string | null;
}

export interface MerchantProductReferenceSearchItem {
  id: string;
  name_fr: string;
  name_ar: string | null;
  brand_id: string;
  brand: string;
  category_id: string;
  category: string;
  category_ar: string | null;
  category_slug: string;
  volume: string | null;
  unit: string;
  barcode: string | null;
  already_in_catalog: boolean;
}

export interface MerchantProductReferenceSearchResult {
  items: MerchantProductReferenceSearchItem[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantProductReferenceSearchOptions {
  q?: string;
  brandId?: string;
  categorySlug?: string;
  page?: number;
  limit?: number;
}

export interface AddMerchantCatalogProductPayload {
  product_reference_id: string;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}

export type MerchantProductUnit =
  | 'litre'
  | 'millilitre'
  | 'kilogramme'
  | 'gramme'
  | 'piece'
  | 'paquet';

export interface CreateMerchantLocalProductPayload {
  name_fr: string;
  name_ar: string | null;
  brand_name: string | null;
  volume: string | null;
  unit: MerchantProductUnit;
  barcode: string | null;
  default_category_name: string | null;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}

export interface MerchantLocalProductOutput {
  merchant_product_id: string;
  local_product_id: string;
  name_fr: string;
  name_ar: string | null;
  brand: string | null;
  category: string;
  volume: string | null;
  unit: string;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}

export interface MerchantBulkAvailabilityPayload {
  merchant_product_ids: string[];
  is_available: boolean;
  merchant_note?: string | null;
}

export interface MerchantBulkAvailabilityResult {
  updated_count: number;
  is_available: boolean;
  merchant_note: string | null;
  merchant_product_ids: string[];
}
