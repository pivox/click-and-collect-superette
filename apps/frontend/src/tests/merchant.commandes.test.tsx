import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import MerchantOrdersPage from '@/app/merchant/commandes/page';
import { listMerchantOrders } from '@/lib/services/merchant-orders.service';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-orders.service', () => ({
  listMerchantOrders: vi.fn(),
}));

describe('MerchantOrdersPage', () => {
  it('renders read-only real order summaries', async () => {
    vi.mocked(listMerchantOrders).mockResolvedValue({
      items: [
        {
          id: 'order-1',
          store_id: 'store-1',
          status: 'submitted',
          total_tnd: '18.500',
          pickup_slot: {
            starts_at: '2026-05-23T10:00:00+01:00',
            ends_at: '2026-05-23T11:00:00+01:00',
          },
          line_count: 4,
          created_at: '2026-05-23T08:00:00+01:00',
          updated_at: '2026-05-23T08:00:00+01:00',
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantOrdersPage));

    await waitFor(() =>
      expect(listMerchantOrders).toHaveBeenCalledWith('store-1', {
        status: 'submitted,accepted,partially_accepted,preparing,ready,pickup_pending',
      }),
    );
    expect(screen.getByRole('heading', { name: 'Commandes' })).toBeInTheDocument();
    expect(screen.getByText('order-1')).toBeInTheDocument();
    expect(screen.getByText('Soumise')).toBeInTheDocument();
    expect(screen.getByText('18,500 TND')).toBeInTheDocument();
    expect(
      screen.getAllByText((_, node) => node?.textContent?.includes('4 produits') ?? false).length,
    ).toBeGreaterThan(0);
    const detailLink = await screen.findByRole('link', { name: /voir la commande order-1/i });
    expect(detailLink).toHaveAttribute('href', '/merchant/commandes/order-1');
  });
});
