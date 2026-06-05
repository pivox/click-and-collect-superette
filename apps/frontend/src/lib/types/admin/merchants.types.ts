// API responses use snake_case (backend @SerializedName annotations)
// Create/Update payloads: some fields use snake_case because the backend DTO has @SerializedName

export interface Merchant {
  id: string;
  email: string;
  first_name: string | null;
  last_name: string | null;
  phone: string | null;
  is_active: boolean;
  subscription_lifecycle: 'active' | 'payment_due' | 'grace_period' | 'suspended' | 'cancelled' | null;
  created_at: string;
  stores_count: number;
  ops_journal?: MerchantOpsJournal | null;
}

export interface MerchantOpsJournal {
  received_orders_count: number;
  accepted_orders_count: number;
  rejected_orders_count: number;
  average_response_minutes: number | null;
  overdue_orders_count: number;
  cancelled_orders_count: number;
  incidents_count: number;
  open_incidents_count: number;
  payment_reminders_count: number;
  admin_actions_count: number;
  health_status: 'healthy' | 'watch' | 'risk';
  last_activity_at: string | null;
  last_activity_status: string | null;
}

export interface MerchantListResponse {
  id: string;
  items: Merchant[];
  page: number;
  limit: number;
  total: number;
}

// AdminCreateMerchantInput has @SerializedName on first_name/last_name/is_active → snake_case in payload
export interface CreateMerchantPayload {
  email: string;
  first_name: string;
  last_name: string;
  phone?: string;
  is_active?: boolean;
}

// AdminUpdateMerchantInput has @SerializedName on first_name/last_name/is_active → snake_case in payload
// Note: email is not updatable (not in AdminUpdateMerchantInput)
export interface UpdateMerchantPayload {
  first_name?: string;
  last_name?: string;
  phone?: string;
  is_active?: boolean;
}
