export type SubscriptionLifecycle =
  | 'active'
  | 'payment_due'
  | 'grace_period'
  | 'suspended'
  | 'cancelled';

export type SubscriptionPricingPhase = 'trial' | 'promo' | 'standard';

export interface MerchantSubscription {
  id: string;
  merchant_id: string;
  merchant_email: string;
  lifecycle: SubscriptionLifecycle;
  pricing_phase: SubscriptionPricingPhase;
  monthly_price_tnd: string;
  currency: 'TND';
  started_at: string;
  current_period_started_at: string;
  current_period_ends_at: string;
  next_phase_change_at: string | null;
  created_at: string;
}

export interface AdminSubscriptionListResponse {
  id: string;
  items: MerchantSubscription[];
  page: number;
  limit: number;
  total: number;
}
