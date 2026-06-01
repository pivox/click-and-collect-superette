import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  notFound: vi.fn(),
}));

vi.mock('@/lib/services', () => ({
  getOrder: vi.fn(),
  projectTimeline: vi.fn(() => [
    { key: 'submitted', label: 'Commande envoyée', state: 'done' },
    { key: 'accepted', label: 'Commande acceptée', state: 'done' },
    { key: 'preparing', label: 'Préparation', state: 'done' },
    { key: 'ready', label: 'Prête', state: 'done' },
    { key: 'completed', label: 'Commande récupérée', state: 'current' },
  ]),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import OrderTrackingPage from '@/app/(client)/orders/[orderId]/page';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { getOrder } from '@/lib/services';
import type { Order } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client Test' };

function makeOrder(status: Order['status']): Order {
  return {
    id: 'order-uuid-1',
    shopId: 'store-uuid-1',
    shopName: 'Supérette El Amen',
    shopAddress: 'Rue de la Liberté',
    shopCity: 'Tunis',
    status,
    totalAmountTnd: '12.500',
    pickupSlot: {
      id: 'slot-uuid-1',
      startsAt: '2026-05-28T10:00:00+01:00',
      endsAt: '2026-05-28T10:30:00+01:00',
      capacity: null,
      available: true,
    },
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: '2026-05-28T10:20:00+01:00',
    rejectionReason: null,
    code: '#0015',
    customerNote: null,
    lines: [],
  };
}

describe('OrderTrackingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useClientAuth).mockReturnValue({
      user: MOCK_USER,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
  });

  it('affiche un état final au lieu du CTA QR en attente pour une commande récupérée', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('completed'));

    render(<OrderTrackingPage params={{ orderId: 'order-uuid-1' }} />);

    expect(await screen.findAllByText('Retrait finalisé')).toHaveLength(2);
    expect(screen.queryByText(/disponible quand prête/i)).toBeNull();
    expect(screen.queryByText(/disponible quand la commande est prête/i)).toBeNull();
    expect(screen.queryByRole('link', { name: /Afficher le QR retrait/i })).toBeNull();

    await waitFor(() => {
      expect(getOrder).toHaveBeenCalledWith('order-uuid-1');
    });
  });
});
