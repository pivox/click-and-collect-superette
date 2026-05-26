// apps/frontend/src/lib/types/merchant-slots.types.ts

export interface MerchantPickupSlotRule {
  id: string;
  weekday: number; // 1 = lundi, 7 = dimanche
  start_time: string; // "HH:MM"
  end_time: string; // "HH:MM"
  capacity: number;
  is_active: boolean;
}

export interface MerchantPickupSlotRuleCollection {
  total: number;
  items: MerchantPickupSlotRule[];
}

export interface MerchantPickupSlot {
  id: string;
  starts_at: string; // ISO 8601
  ends_at: string;
  capacity: number;
  booked_count: number;
  is_active: boolean;
}

export interface MerchantExceptionalClosure {
  id: string;
  starts_at: string;
  ends_at: string;
  reason: string | null;
  is_active: boolean;
}

export interface MerchantExceptionalClosureCollection {
  total: number;
  items: MerchantExceptionalClosure[];
}

export interface CreateSlotRulePayload {
  weekday: number;
  start_time: string;
  end_time: string;
  capacity: number;
}

export interface PatchSlotRulePayload {
  weekday?: number;
  start_time?: string;
  end_time?: string;
  capacity?: number;
  is_active?: boolean;
}

export interface CreateSlotPayload {
  starts_at: string;
  ends_at: string;
  capacity: number;
}

export interface PatchSlotPayload {
  capacity?: number;
  is_active?: boolean;
}

export interface CreateClosurePayload {
  starts_at: string;
  ends_at: string;
  reason?: string;
}

export interface PatchClosurePayload {
  starts_at?: string;
  ends_at?: string;
  reason?: string | null;
  is_active?: boolean;
}

export interface GenerateSlotsResult {
  store_id: string;
  generated_count: number;
  skipped_existing_count: number;
  skipped_closure_count: number;
  horizon_start: string;
  horizon_end: string;
}
