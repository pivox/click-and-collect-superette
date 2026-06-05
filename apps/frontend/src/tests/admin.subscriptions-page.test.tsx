import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminSubscriptionsPage from '@/app/admin/abonnements/page';
import {
  getAdminSubscription,
  listAdminSubscriptions,
} from '@/lib/services/subscriptions.service';
import type { MerchantSubscription } from '@/lib/types/subscriptions.types';

vi.mock('@/lib/services/subscriptions.service', () => ({
  listAdminSubscriptions: vi.fn(),
  getAdminSubscription: vi.fn(),
}));

const SUBSCRIPTION: MerchantSubscription = {
  id: 'sub-1',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  lifecycle: 'payment_due',
  pricing_phase: 'promo',
  monthly_price_tnd: '10.000',
  currency: 'TND',
  started_at: '2026-06-01T00:00:00+01:00',
  current_period_started_at: '2026-06-01T00:00:00+01:00',
  current_period_ends_at: '2026-07-01T00:00:00+01:00',
  next_phase_change_at: '2026-09-01T00:00:00+01:00',
  created_at: '2026-06-01T00:00:00+01:00',
};

describe('AdminSubscriptionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listAdminSubscriptions).mockResolvedValue({
      id: 'admin-subscriptions',
      items: [SUBSCRIPTION],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getAdminSubscription).mockResolvedValue(SUBSCRIPTION);
  });

  it('renders merchant subscriptions with business labels and TND amounts', async () => {
    render(<AdminSubscriptionsPage />);

    expect(await screen.findByText('ali@example.test')).toBeInTheDocument();
    expect(screen.getByText('Paiement attendu')).toBeInTheDocument();
    expect(screen.getByText('Promotion')).toBeInTheDocument();
    expect(screen.getByText('10,000 TND')).toBeInTheDocument();
    expect(screen.getByText('01/07/2026')).toBeInTheDocument();
  });

  it('opens a read-only detail panel for an admin subscription', async () => {
    render(<AdminSubscriptionsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir le détail abonnement' }));

    await waitFor(() => expect(getAdminSubscription).toHaveBeenCalledWith('sub-1'));
    const detail = await screen.findByRole('dialog', { name: 'Détail abonnement' });
    expect(within(detail).getByText('ali@example.test')).toBeInTheDocument();
    expect(within(detail).getByText('Phase tarifaire')).toBeInTheDocument();
    expect(within(detail).getByText('Promotion')).toBeInTheDocument();
  });

  it('shows an actionable empty state', async () => {
    vi.mocked(listAdminSubscriptions).mockResolvedValue({
      id: 'admin-subscriptions',
      items: [],
      page: 1,
      limit: 20,
      total: 0,
    });

    render(<AdminSubscriptionsPage />);

    expect(await screen.findByText('Aucun abonnement marchand à afficher.')).toBeInTheDocument();
  });
});
