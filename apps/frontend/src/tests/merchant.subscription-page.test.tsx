import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantSubscriptionPage from '@/app/merchant/parametres/abonnement/page';
import MerchantSettingsPage from '@/app/merchant/parametres/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';
import { getMerchantSubscription } from '@/lib/services/subscriptions.service';
import type { MerchantSubscription } from '@/lib/types/subscriptions.types';

vi.mock('@/lib/services/subscriptions.service', () => ({
  getMerchantSubscription: vi.fn(),
}));

const SUBSCRIPTION: MerchantSubscription = {
  id: 'sub-merchant',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  lifecycle: 'active',
  pricing_phase: 'standard',
  monthly_price_tnd: '50.000',
  currency: 'TND',
  started_at: '2026-01-01T00:00:00+01:00',
  current_period_started_at: '2026-06-01T00:00:00+01:00',
  current_period_ends_at: '2026-07-01T00:00:00+01:00',
  next_phase_change_at: null,
  created_at: '2026-01-01T00:00:00+01:00',
};

function renderWithLocale(children: React.ReactNode) {
  return render(<MerchantLocaleProvider>{children}</MerchantLocaleProvider>);
}

describe('MerchantSubscriptionPage', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
    vi.mocked(getMerchantSubscription).mockResolvedValue(SUBSCRIPTION);
  });

  it('renders the current merchant subscription in read-only mode', async () => {
    renderWithLocale(<MerchantSubscriptionPage />);

    expect(await screen.findByText('Abonnement marchand')).toBeInTheDocument();
    expect(screen.getByText('Actif')).toBeInTheDocument();
    expect(screen.getByText('Standard')).toBeInTheDocument();
    expect(screen.getByText('50,000 TND')).toBeInTheDocument();
    expect(screen.getByText('01/07/2026')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /payer/i })).not.toBeInTheDocument();
  });

  it('shows the subscription shortcut from merchant settings', () => {
    renderWithLocale(<MerchantSettingsPage />);

    expect(screen.getByRole('link', { name: /Abonnement marchand/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/abonnement',
    );
  });
});
