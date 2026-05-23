// API responses use snake_case (backend @SerializedName annotations)
// Request payloads use camelCase (PHP property names, no @SerializedName on inputs)

export interface Merchant {
  id: string;
  email: string;
  first_name: string;
  last_name: string;
  shop_count: number;
  is_suspended: boolean;
  suspended_at: string | null;
  created_at: string;
}

export interface MerchantListResponse {
  items: Merchant[];
  page: number;
  limit: number;
  total: number;
}

export interface CreateMerchantPayload {
  email: string;
  firstName: string;
  lastName: string;
  password: string;
}

export interface UpdateMerchantPayload {
  email?: string;
  firstName?: string;
  lastName?: string;
}
