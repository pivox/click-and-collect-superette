export type MerchantCatalogAvailabilityFilter = 'all' | 'available' | 'unavailable';
export type MerchantCatalogVisibilityFilter = 'all' | 'visible' | 'hidden';
export type MerchantCatalogCompletionFilter = 'all' | 'needs_price';
export type MerchantCatalogPromotionFilter = 'all' | 'active';

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
  promotion_price_tnd?: string | null;
  promotion_ends_on?: string | null;
  promotion_active?: boolean;
  effective_price_tnd?: string;
  is_available: boolean;
  is_visible: boolean;
  requires_price_completion?: boolean;
  merchant_note: string | null;
}

export interface MerchantCategory {
  id: string;
  name_fr: string;
  name_ar: string | null;
  slug: string;
  parent_id: string | null;
  sort_order: number;
  active: boolean;
  created_at: string;
  updated_at: string;
}

export interface CreateMerchantCategoryPayload {
  name_fr: string;
  name_ar?: string | null;
  parent_id?: string | null;
  sort_order?: number | null;
  active?: boolean;
}

export interface MerchantCatalogListOptions {
  q?: string;
  availability?: MerchantCatalogAvailabilityFilter;
  visibility?: MerchantCatalogVisibilityFilter;
  completion?: MerchantCatalogCompletionFilter;
  promotion?: MerchantCatalogPromotionFilter;
  category?: string;
}

export interface MerchantCatalogListResult {
  items: MerchantCatalogProduct[];
  total: number;
  page: number;
  limit: number;
  pages: number;
}

export type MerchantProductGroupLineStatus = 'new' | 'already_present' | 'non_importable';

export interface MerchantProductGroupReference {
  id: string;
  name_fr: string;
  name_ar: string | null;
  brand_name: string;
  category_name_fr: string;
  unit: string;
  volume: string | null;
  status: string;
}

export interface MerchantProductGroupItem {
  id: string;
  sort_order: number;
  importance: string;
  status: MerchantProductGroupLineStatus;
  product_reference: MerchantProductGroupReference;
}

export interface MerchantProductGroup {
  id: string;
  name_fr: string;
  name_ar: string | null;
  slug: string;
  description_fr: string | null;
  description_ar: string | null;
  market_country: string;
  icon: string | null;
  sort_order: number;
  items_count: number;
  items?: MerchantProductGroupItem[];
}

export interface MerchantProductGroupListResponse {
  id: string;
  items: MerchantProductGroup[];
}

export interface MerchantProductGroupImportPayload {
  groupId: string;
  selectedProductReferenceIds: string[];
  skipExisting: boolean;
  defaultVisibility: boolean;
  defaultAvailability: boolean;
}

export interface MerchantProductGroupImportError {
  productReferenceId: string;
  code: string;
  message: string;
}

export interface MerchantProductGroupImportResult {
  created: number;
  alreadyInCatalog: number;
  skipped: number;
  requiresPriceCompletion: number;
  errors: MerchantProductGroupImportError[];
}

export interface UpdateMerchantCatalogProductPayload {
  price_tnd?: string;
  promotion_price_tnd?: string | null;
  promotion_ends_on?: string | null;
  is_available?: boolean;
  is_visible?: boolean;
  merchant_note?: string | null;
  merchant_category_id?: string | null;
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
  barcode?: string;
  brandId?: string;
  categorySlug?: string;
  page?: number;
  limit?: number;
}

export interface MerchantCatalogCsvImportItem {
  line: number;
  status: string;
  merchant_product_id: string;
  product_reference_id: string | null;
  local_product_id: string | null;
  name_fr: string;
}

export interface MerchantCatalogCsvImportError {
  line: number;
  code: string;
  field: string | null;
  message: string;
}

export interface MerchantCatalogCsvImportResult {
  id: string;
  created: number;
  updated: number;
  ignored: number;
  items: MerchantCatalogCsvImportItem[];
  errors: MerchantCatalogCsvImportError[];
}

export type MerchantCatalogPhotoImportSourceType =
  | 'receipt'
  | 'shelf'
  | 'cash_register_export'
  | 'paper_list';

export interface MerchantCatalogPhotoImportPreviewItem {
  line: number;
  status: 'matched_reference' | 'local_candidate' | 'already_in_catalog';
  product_reference_id: string | null;
  name_fr: string;
  brand: string | null;
  volume: string | null;
  unit: string | null;
  barcode: string | null;
  suggested_price_tnd: string | null;
  confidence: string | null;
  already_in_catalog: boolean;
}

export interface MerchantCatalogPhotoImportPreviewResult {
  id: string;
  source_type: MerchantCatalogPhotoImportSourceType;
  detected_count: number;
  matched_reference_count: number;
  local_candidate_count: number;
  items: MerchantCatalogPhotoImportPreviewItem[];
}

export interface MerchantCatalogPhotoImportCommitItemPayload {
  line: number;
  selected: boolean;
  status?: MerchantCatalogPhotoImportPreviewItem['status'];
  product_reference_id: string | null;
  name_fr: string;
  brand: string | null;
  volume: string | null;
  unit: string | null;
  barcode: string | null;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  category?: string | null;
  merchant_note?: string | null;
  pack_quantity?: number;
}

export interface MerchantCatalogPhotoImportCommitPayload {
  items: MerchantCatalogPhotoImportCommitItemPayload[];
}

export interface AddMerchantCatalogProductPayload {
  product_reference_id: string;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
  merchant_category_id?: string | null;
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
  merchant_category_id?: string | null;
  pack_quantity?: number;
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
  pack_quantity: number;
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

export interface BulkLocalProductFormatPayload {
  volume: string | null;
  unit: MerchantProductUnit;
  barcode: string | null;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
  pack_quantity: number;
}

export interface CreateBulkLocalProductPayload {
  base_name_fr: string;
  base_name_ar: string | null;
  brand_name: string | null;
  default_category_name: string | null;
  merchant_category_id: string | null;
  formats: BulkLocalProductFormatPayload[];
}

export interface BulkLocalProductCreatedItem {
  merchant_product_id: string;
  local_product_id: string;
  name_fr: string;
  price_tnd: string;
}

export interface BulkLocalProductCreatedOutput {
  created_count: number;
  items: BulkLocalProductCreatedItem[];
}

export interface CreateProductProposalPayload {
  name_fr: string;
  name_ar?: string | null;
  brand_name?: string | null;
  category_id?: string | null;
  category_name_proposed?: string | null;
  local_product_id?: string | null;
  variant_fr?: string | null;
  volume?: string | null;
  unit?: MerchantProductUnit;
  barcode?: string | null;
}

export interface GlobalCategory {
  id: string;
  name_fr: string;
  name_ar: string | null;
  slug: string;
  parent_id: string | null;
  sort_order: number;
}

export interface GlobalBrand {
  id: string;
  name: string;
  slug: string;
}

export interface MerchantProductPriceHistoryItem {
  oldPrice: string | null;
  newPrice: string;
  currency: string;
  changeType: string;
  source: string;
  reason: string | null;
  changedByUserId: string | null;
  changedAt: string;
}

export interface MerchantProductPriceHistoryResult {
  merchantProductId: string;
  currentPrice: string;
  currency: string;
  priceHistory: MerchantProductPriceHistoryItem[];
}
