// API responses use snake_case (backend @SerializedName annotations)
// Create/Update payloads: some fields use snake_case because the backend DTO has @SerializedName
import type { Store } from '@/lib/types/admin/stores.types';

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
  crm?: MerchantCrm | null;
}

export type MerchantCrmStatus = 'prospect' | 'client';

export type MerchantCrmContactChannel = 'phone' | 'whatsapp' | 'visit' | 'email' | 'other';

export interface MerchantCrm {
  commercial_owner: string | null;
  status: MerchantCrmStatus;
  last_contact_at: string | null;
  next_action_at: string | null;
  next_action_note: string | null;
  commercial_note: string | null;
  // Item-only: returned by GET /{id} and CRM mutations, absent from the list response
  contacts?: MerchantCrmContact[];
}

export interface MerchantCrmContact {
  id: string;
  contacted_at: string;
  channel: MerchantCrmContactChannel;
  note: string;
  author_email: string;
  created_at: string;
}

// AdminMerchantCrmUpdateInput — partial update: only provided keys are applied,
// an explicit null clears the field (next_action_at, etc.)
export interface UpdateMerchantCrmPayload {
  commercial_owner?: string | null;
  status?: MerchantCrmStatus;
  next_action_at?: string | null;
  next_action_note?: string | null;
  commercial_note?: string | null;
}

export interface CreateMerchantCrmContactPayload {
  channel: MerchantCrmContactChannel;
  note: string;
  contacted_at?: string;
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

export interface MerchantTemporaryPasswordResponse {
  merchant_id: string;
  temporary_password: string;
  expires_at?: string;
}

export type FirstLoginMode = 'temporary_password' | 'email_invitation';

export interface MerchantOnboardingPayload {
  merchant: {
    email: string;
    first_name: string;
    last_name: string;
    phone?: string;
  };
  shop: {
    name: string;
    address?: string;
    city?: string;
    phone?: string;
  };
  first_login_mode: FirstLoginMode;
  product_group_ids: string[];
}

export interface MerchantOnboardingResponse {
  id: string;
  merchant: Merchant;
  shop: Store;
  first_login: {
    mode: FirstLoginMode;
    temporary_password?: string | null;
    expires_at?: string | null;
    invitation_status?: 'sent' | null;
  };
  catalog_preload: {
    added_count: number;
    already_existing_count: number;
    ignored_count: number;
    errors: Array<{
      product_reference_id: string;
      code: string;
      message: string;
      product_group_id?: string | null;
    }>;
  };
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
