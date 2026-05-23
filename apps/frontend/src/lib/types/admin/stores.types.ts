// API responses use snake_case (backend @SerializedName annotations)
// Request payloads use camelCase (PHP property names, no @SerializedName on inputs)

export interface Store {
  id: string;
  name: string;
  description: string | null;
  address: string | null;
  city: string | null;
  logo_url: string | null;
  cover_url: string | null;
  merchant_id: string;
  merchant_name: string;
  archived_at: string | null;
  archive_reason: string | null;
  created_at: string;
}

export interface StoreListResponse {
  items: Store[];
  page: number;
  limit: number;
  total: number;
}

export interface StoreFilters {
  page?: number;
  limit?: number;
  merchant?: string;
  status?: string;
}

export interface CreateStorePayload {
  name: string;
  merchantId: string;
  description?: string;
  address?: string;
  city?: string;
  logoUrl?: string;
  coverUrl?: string;
}

export interface UpdateStorePayload {
  name?: string;
  description?: string;
  address?: string;
  city?: string;
  logoUrl?: string;
  coverUrl?: string;
}
