// API responses use snake_case (backend @SerializedName annotations)
// Request payloads use camelCase (PHP property names, no @SerializedName on inputs)

export interface Category {
  id: string;
  name_fr: string;
  name_ar: string | null;
  slug: string;
  is_active: boolean;
  sort_order: number;
  parent_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface CategoryListResponse {
  id: string;
  items: Category[];
  page: number;
  limit: number;
  total: number;
}

export interface CreateCategoryPayload {
  nameFr: string;
  nameAr?: string;
  slug?: string;
}

export interface UpdateCategoryPayload {
  nameFr?: string;
  nameAr?: string;
  isActive?: boolean;
}

export interface Brand {
  id: string;
  canonical_name: string;
  slug: string;
  aliases: string[];
  country: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface BrandListResponse {
  id: string;
  items: Brand[];
  page: number;
  limit: number;
  total: number;
}

export interface CreateBrandPayload {
  canonicalName: string;
  slug?: string;
  aliases?: string[];
  country?: string;
}

export interface UpdateBrandPayload {
  canonicalName?: string;
  slug?: string;
  aliases?: string[];
  country?: string;
  isActive?: boolean;
}

export interface ProductReference {
  id: string;
  name_fr: string;
  name_ar: string | null;
  variant_fr: string | null;
  variant_ar: string | null;
  brand_id: string;
  brand_name: string;
  category_id: string;
  category_name_fr: string;
  category_name_ar: string | null;
  unit: string;
  volume: string | null;
  barcode: string | null;
  aliases: string[];
  country: string;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface ProductReferenceListResponse {
  id: string;
  items: ProductReference[];
  page: number;
  limit: number;
  total: number;
}

export interface ProductReferenceFilters {
  q?: string;
  brand?: string;
  category?: string;
  status?: string;
  page?: number;
  limit?: number;
}

export interface CreateProductReferencePayload {
  nameFr: string;
  nameAr?: string;
  variantFr?: string;
  variantAr?: string;
  brandId: string;
  categoryId: string;
  unit: string;
  volume?: string;
  barcode?: string;
  aliases?: string[];
  country?: string;
  status?: string;
}

export interface UpdateProductReferencePayload {
  nameFr?: string;
  nameAr?: string;
  variantFr?: string;
  variantAr?: string;
  brandId?: string;
  categoryId?: string;
  unit?: string;
  volume?: string;
  barcode?: string;
  aliases?: string[];
  country?: string;
  status?: string;
}

export interface ProductAiEnrichmentRunPayload {
  limit: number;
}

export interface ProductAiEnrichmentRunResult {
  jobs_created: number;
  jobs_submitted: number;
  jobs_applied_total: number;
  jobs_failed_total: number;
  active_batches_checked: number;
  openai_skipped: boolean;
}

export interface Proposal {
  id: string;
  name_fr: string;
  name_ar: string | null;
  brand_name: string | null;
  category: string;
  status: string;
  rejection_reason: string | null;
  created_at: string;
  proposed_by: string;
  created_product_reference_id: string | null;
}

export interface ApproveProposalPayload {
  productReferenceId?: string;
  canonicalData?: {
    nameFr: string;
    brandId: string;
    categoryId: string;
  };
}
