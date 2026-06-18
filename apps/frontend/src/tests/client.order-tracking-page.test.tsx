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
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';
import type { Order } from '@/types';

function renderPage(orderId: string) {
  return render(
    <ClientLocaleProvider>
      <OrderTrackingPage params={{ orderId }} />
    </ClientLocaleProvider>,
  );
}

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

    renderPage('order-uuid-1');

    expect(await screen.findAllByText('Retrait finalisé')).toHaveLength(2);
    expect(screen.queryByText(/disponible quand prête/i)).toBeNull();
    expect(screen.queryByText(/disponible quand la commande est prête/i)).toBeNull();
    expect(screen.queryByRole('link', { name: /Afficher le QR retrait/i })).toBeNull();

    await waitFor(() => {
      expect(getOrder).toHaveBeenCalledWith('order-uuid-1');
    });
  });

  it('affiche la raison et les articles refusés pour une commande partiellement acceptée', async () => {
    vi.mocked(getOrder).mockResolvedValue({
      ...makeOrder('partially_accepted'),
      rejectionReason: 'Rupture de stock.',
      kadhiaId: 'kadhia-uuid-1',
      rejectedLines: [
        {
          id: 'line-rejected-1',
          productOffer: {
            id: 'mp-rejected',
            productReferenceId: 'mp-rejected',
            nameFr: 'Yaourt nature',
            nameAr: null,
            brand: '',
            volume: null,
            unit: null,
            priceTnd: '1.800',
            isAvailable: true,
            photoUrl: null,
            category: 'epicerie',
          },
          quantity: 1,
          unitPriceTnd: '1.800',
          lineTotalTnd: '1.800',
        },
      ],
    } as Order & { rejectedLines: Order['lines'] });

    renderPage('order-uuid-1');

    expect(await screen.findByText('Commande partiellement acceptée')).toBeTruthy();
    expect(screen.getByText('Rupture de stock.')).toBeTruthy();
    expect(screen.getByText('Yaourt nature')).toBeTruthy();
    expect(screen.getByText(/Kadhia contient uniquement les articles conservés/i)).toBeTruthy();
    expect(screen.getAllByRole('link', { name: /Voir ma Kadhia/i })[0].getAttribute('href')).toBe(
      '/kadhia/kadhia-uuid-1?context=partially_accepted',
    );
  });

  it('affiche les notes client et marchand sans les confondre', async () => {
    vi.mocked(getOrder).mockResolvedValue({
      ...makeOrder('rejected'),
      customerNote: 'Si possible, remplacer par une marque proche.',
      rejectionReason: 'Produit indisponible chez le marchand.',
    });

    renderPage('order-uuid-1');

    expect(await screen.findByText('Ta note')).toBeTruthy();
    expect(screen.getByText('Si possible, remplacer par une marque proche.')).toBeTruthy();
    expect(screen.getByText('Note du marchand')).toBeTruthy();
    expect(screen.getByText('Produit indisponible chez le marchand.')).toBeTruthy();
  });
});
