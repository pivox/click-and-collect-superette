import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import { SubscriptionPricingSummary } from '@/components/subscriptions/SubscriptionPricingSummary';
import type { MerchantSubscription } from '@/lib/types/subscriptions.types';

it('renders annual terms and per-month pricing in Arabic with RTL', () => {
  const subscription: MerchantSubscription = {
    id: 's', merchant_id: 'm', merchant_email: 'merchant@example.test', lifecycle: 'active',
    pricing_phase: 'promo', monthly_price_tnd: '31.250', currency: 'TND',
    started_at: '2026-10-01T00:00:00+01:00', created_at: '2026-10-01T00:00:00+01:00',
    current_period_started_at: '2026-10-01T00:00:00+01:00', current_period_ends_at: '2027-10-01T00:00:00+01:00',
    next_phase_change_at: null, billing_interval: 'annual', period_price_tnd: '375.000',
    pricing_terms: { billing_interval: 'annual', pricing_mode: 'discounted', base_price_tnd: '600.000',
      discounts: [{ from_month: 1, to_month: 3, type: 'percentage', value: '100' }], free_months: null,
      effective_at: '2026-10-01T00:00:00+01:00' },
    period_breakdown: [{ month: 1, starts_at: '2026-10-01T00:00:00+01:00', ends_at: '2026-11-01T00:00:00+01:00', base_amount_tnd: '50.000', discount_amount_tnd: '50.000', amount_tnd: '0.000' }],
  };
  const { container } = render(<SubscriptionPricingSummary subscription={subscription} locale="ar" />);
  expect(screen.getByText('المبلغ السنوي')).toBeInTheDocument();
  expect(screen.getByText(/بتخفيض/)).toBeInTheDocument();
  expect(screen.getByText('تفاصيل المبلغ حسب الشهر', { selector: 'summary' })).toBeInTheDocument();
  expect(container.firstChild).toHaveAttribute('dir', 'rtl');
  expect(screen.queryByText('Montant annuel')).not.toBeInTheDocument();
});
