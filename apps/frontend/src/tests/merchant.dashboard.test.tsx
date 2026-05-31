import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantDashboardPage from '@/app/merchant/page';
import { getMerchantDashboardToday } from '@/lib/services/merchant-dashboard.service';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-dashboard.service', () => ({
  getMerchantDashboardToday: vi.fn(),
}));

const dashboardToday = {
  store_id: 'store-1',
  date: '2026-05-23',
  total_orders_today: 6,
  orders_by_status: {},
  submitted_count: 3,
  accepted_count: 1,
  partially_accepted_count: 0,
  preparing_count: 1,
  ready_count: 1,
  cancelled_count: 0,
  rejected_count: 0,
  completed_count: 0,
  pickup_pending_count: 0,
  urgent_submitted_count: 2,
  pickup_slots_today: [
    {
      pickup_slot_id: 'slot-1',
      starts_at: '2026-05-23T10:00:00+01:00',
      ends_at: '2026-05-23T11:00:00+01:00',
      capacity: 5,
      booked_count: 2,
      remaining_capacity: 3,
    },
  ],
};

describe('MerchantDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders compact dashboard counts from the backend', async () => {
    vi.mocked(getMerchantDashboardToday).mockResolvedValue(dashboardToday);

    render(React.createElement(MerchantDashboardPage));

    await waitFor(() => expect(getMerchantDashboardToday).toHaveBeenCalledWith('store-1'));
    expect(screen.getByRole('heading', { name: 'Dashboard marchand' })).toBeInTheDocument();
    // KPI counter labels (unchanged)
    expect(screen.getByText('En attente')).toBeInTheDocument();
    expect(screen.getByText('Urgentes')).toBeInTheDocument();
    // Counts may appear in both KPI grid and "À faire maintenant" section
    expect(screen.getAllByText('3').length).toBeGreaterThan(0);
    expect(screen.getAllByText('2').length).toBeGreaterThan(0);
    expect(screen.getByText(/2\/5 rendez-vous/)).toBeInTheDocument();
  });

  it('shows Actualiser once the dashboard is loaded without error', async () => {
    vi.mocked(getMerchantDashboardToday).mockResolvedValue(dashboardToday);

    render(React.createElement(MerchantDashboardPage));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Actualiser' })).toBeInTheDocument());
    expect(screen.queryByRole('button', { name: 'Réessayer' })).not.toBeInTheDocument();
  });

  it('keeps Réessayer for the visible error state', async () => {
    vi.mocked(getMerchantDashboardToday).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantDashboardPage));

    expect(await screen.findByText('Impossible de charger le dashboard marchand.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });
});
