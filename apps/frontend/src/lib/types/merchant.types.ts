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

export type MerchantOrderStatus =
  | 'draft'
  | 'submitted'
  | 'accepted'
  | 'partially_accepted'
  | 'rejected'
  | 'preparing'
  | 'ready'
  | 'pickup_pending'
  | 'completed'
  | 'cancelled';

export interface MerchantOrderDetailPickupSlot {
  id: string;
  starts_at: string;
  ends_at: string;
}

export interface MerchantOrderLine {
  merchant_product_id: string;
  product_name: string | null;
  quantity: number;
  unit_price_tnd: string;
  line_total_tnd: string;
  prepared: boolean;
}

export interface MerchantOrderDetail {
  id: string;
  store_id: string;
  status: MerchantOrderStatus;
  total_tnd: string;
  pickup_slot: MerchantOrderDetailPickupSlot | null;
  notes: string | null;
  lines: MerchantOrderLine[];
  customer_name: string | null;
  customer_phone: string | null;
  rejection_reason: string | null;
  created_at: string;
  updated_at: string;
  order_number?: string;
}

export interface RejectMerchantOrderPayload {
  reason: string | null;
}

export interface PartiallyAcceptMerchantOrderPayload {
  rejected_merchant_product_ids: string[];
  notes: string | null;
}

export interface SetMerchantOrderLinePreparedPayload {
  prepared: boolean;
}

export interface MerchantOrderMutationResult {
  id: string;
  status: MerchantOrderStatus;
}

export interface MerchantPickupSessionCustomer {
  first_name: string | null;
  last_name: string | null;
  phone: string | null;
}

export interface MerchantPickupSessionLine {
  merchant_product_id: string;
  name: string;
  quantity: number;
  unit_price_tnd: string;
}

export interface MerchantPickupSessionScanResult {
  id: string;
  order_id: string;
  store_id: string;
  order_number: string | null;
  status: 'pickup_pending';
  scanned_at: string;
  customer: MerchantPickupSessionCustomer;
  lines: MerchantPickupSessionLine[];
}

export interface MerchantPickupSessionActionResult {
  id: string;
  order_id: string;
  order_status: MerchantOrderStatus;
  scanned_at: string;
  merchant_confirmed_at: string | null;
  customer_confirmed_at: string | null;
  is_used: boolean;
  is_completed: boolean;
}

export interface MerchantPickupSessionForceCompleteResult
  extends MerchantPickupSessionActionResult {
  force_completed_by_merchant: boolean;
  force_note: string | null;
}
