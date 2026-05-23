export interface MerchantLoginPayload {
  email: string;
  password: string;
}

export interface MerchantLoginUser {
  token: string;
  email: string;
}

export interface MerchantStoreContext {
  id: string;
  name: string;
  active: boolean;
}

export interface MerchantMe {
  user_id: string;
  email: string;
  roles: string[];
  store: MerchantStoreContext;
  onboarding_completed: boolean;
}

export interface MerchantDashboardPickupSlot {
  pickup_slot_id: string;
  starts_at: string;
  ends_at: string;
  capacity: number;
  booked_count: number;
  remaining_capacity: number;
}

export interface MerchantDashboardToday {
  store_id: string;
  date: string;
  total_orders_today: number;
  orders_by_status: Record<string, number>;
  submitted_count: number;
  accepted_count: number;
  partially_accepted_count: number;
  preparing_count: number;
  ready_count: number;
  cancelled_count: number;
  rejected_count: number;
  completed_count: number;
  pickup_pending_count: number;
  urgent_submitted_count: number;
  pickup_slots_today: MerchantDashboardPickupSlot[];
}

export interface MerchantOrderPickupSlot {
  id?: string;
  starts_at?: string;
  ends_at?: string;
}

export interface MerchantOrderSummary {
  id: string;
  store_id: string;
  status: string;
  total_tnd: string;
  pickup_slot: MerchantOrderPickupSlot | null;
  line_count: number;
  created_at: string;
  updated_at: string;
  order_number?: string;
  customer_name?: string | null;
}

export interface MerchantOrderList {
  items: MerchantOrderSummary[];
  total: number;
  page: number;
  limit: number;
}

export type MerchantOrderHistoryList = MerchantOrderList;
