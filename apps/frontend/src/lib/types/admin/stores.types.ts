// API responses use snake_case (backend @SerializedName annotations)
// Request payloads use camelCase (PHP property names, no @SerializedName on AdminStore inputs)

export interface StoreOwner {
  id: string;
  email: string;
}

export interface Store {
  id: string;
  name: string;
  slug: string;
  city: string | null;
  is_active: boolean;
  qr_code_token: string;
  created_at: string;
  owner: StoreOwner | null;
  products_count: number;
  archived_at: string | null;
  // item-only fields (present on single GET / POST / PATCH responses)
  address?: string | null;
  phone?: string | null;
  logo_url?: string | null;
  cover_url?: string | null;
  archive_reason?: string | null;
}

export interface StoreListResponse {
  id: string;
  items: Store[];
  page: number;
  limit: number;
  total: number;
}

export interface StoreFilters {
  page?: number;
  limit?: number;
  isActive?: boolean;
}

// AdminStoreCreateInput has no @SerializedName → camelCase in payload
// Note: no logoUrl/coverUrl in create input (update only)
export interface CreateStorePayload {
  name: string;
  ownerId: string;
  address?: string;
  city?: string;
  phone?: string;
}

// AdminStoreUpdateInput → camelCase in payload
export interface UpdateStorePayload {
  name?: string;
  address?: string;
  city?: string;
  phone?: string;
  isActive?: boolean;
  ownerId?: string;
  logoUrl?: string | null;   // null to clear
  coverUrl?: string | null;  // null to clear
}
