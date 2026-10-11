export type SubscriptionLifecycle =
  | 'active'
  | 'payment_due'
  | 'grace_period'
  | 'suspended'
  | 'cancelled';

export type SubscriptionPricingPhase = 'trial' | 'promo' | 'standard';

export type SubscriptionBillingInterval = 'monthly' | 'annual';
export type SubscriptionPricingMode = 'paid' | 'discounted' | 'free';

export interface SubscriptionDiscount {
  from_month: number;
  to_month: number;
  type: 'percentage' | 'fixed';
  value: string;
}

export interface SubscriptionPricingInput {
  billing_interval: SubscriptionBillingInterval;
  pricing_mode: SubscriptionPricingMode;
  base_price_tnd: string;
  free_months: number | null;
  discounts: SubscriptionDiscount[];
  reason: string;
}

export interface SubscriptionPricingTerms extends Omit<SubscriptionPricingInput, 'reason'> {
  effective_at: string;
}

export interface SubscriptionMonthAmount {
  month: number;
  starts_at: string;
  ends_at: string;
  base_amount_tnd: string;
  discount_amount_tnd: string;
  amount_tnd: string;
}

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
  billing_interval?: SubscriptionBillingInterval;
  pricing_mode?: SubscriptionPricingMode;
  base_price_tnd?: string;
  period_price_tnd?: string;
  pricing_terms?: SubscriptionPricingTerms | null;
  scheduled_pricing?: SubscriptionPricingTerms | null;
  period_breakdown?: SubscriptionMonthAmount[];
}

export interface AdminSubscriptionListResponse {
  id: string;
  items: MerchantSubscription[];
  page: number;
  limit: number;
  total: number;
}
