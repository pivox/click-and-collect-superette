import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { mockReplace, mockNotFound } = vi.hoisted(() => ({
  mockReplace: vi.fn(),
  mockNotFound: vi.fn(),
}));

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace: mockReplace, push: vi.fn() }),
  notFound: mockNotFound,
  redirect: vi.fn(),
}));

vi.mock('@/lib/services', () => ({
  getOrder: vi.fn(),
  USE_MOCKS: false,
  mockDelay: (v: unknown) => Promise.resolve(v),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import PickupQrPage from '@/app/(client)/orders/[orderId]/pickup/page';
import { getOrder } from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import type { OrderStatus } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client Test' };

function makeOrder(status: OrderStatus) {
  return {
    id: 'order-uuid-1',
    shopId: 'store-1',
    shopName: 'Supérette El Amen',
    shopAddress: 'Rue de la Liberté',
    shopCity: 'Tunis',
    status,
    totalAmountTnd: '12.500',
    pickupSlot: {
      id: 'slot-1',
      startsAt: '2026-05-28T10:00:00+01:00',
      endsAt: '2026-05-28T10:30:00+01:00',
      capacity: null,
      available: true,
    },
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectionReason: null,
    code: 'CMD-ORDER1',
    customerNote: null,
    lines: [],
  };
}

describe('PickupQrPage', () => {
  function mockAuth(user: typeof MOCK_USER | null, isLoading = false) {
    vi.mocked(useClientAuth).mockReturnValue({
      user,
      isLoading,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
  }

  beforeEach(() => {
    vi.clearAllMocks();
    mockAuth(MOCK_USER);
  });

  it('affiche un état de chargement (null) pendant authLoading', () => {
    mockAuth(null, true);
    const { container } = render(
      <PickupQrPage params={{ orderId: 'order-uuid-1' }} />,
    );
    expect(container.firstChild).toBeNull();
  });

  it('affiche un lien de connexion si non authentifié', () => {
    mockAuth(null);
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    expect(screen.getByText(/Connecte-toi/i)).toBeTruthy();
    const link = screen.getByRole('link', { name: /Connecte-toi/i });
    expect(link.getAttribute('href')).toBe(
      '/login?redirect=/orders/order-uuid-1/pickup',
    );
  });

  it('appelle notFound si getOrder retourne null (commande introuvable)', async () => {
    vi.mocked(getOrder).mockResolvedValue(null);
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(mockNotFound).toHaveBeenCalled();
    });
  });

  it('redirige via router.replace si le statut n\'est pas éligible au retrait', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('preparing'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/orders/order-uuid-1');
    });
  });

  it('affiche le QR code pour une commande au statut ready', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('ready'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(screen.getByText(/Présente ce code au comptoir/i)).toBeTruthy();
    });
    expect(screen.getByText('CMD-ORDER1')).toBeTruthy();
    expect(screen.getByText('Supérette El Amen')).toBeTruthy();
    expect(screen.getByText('Rue de la Liberté, Tunis')).toBeTruthy();
  });

  it('affiche le QR code pour une commande au statut pickup_pending', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('pickup_pending'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(screen.getByText(/Présente ce code au comptoir/i)).toBeTruthy();
    });
  });

  it('affiche un message d\'erreur réseau avec bouton Réessayer', async () => {
    vi.mocked(getOrder).mockRejectedValue(new Error('Network Error'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(screen.getByText(/Le chargement a échoué/i)).toBeTruthy();
    });
    expect(screen.getByRole('button', { name: /Réessayer/i })).toBeTruthy();
  });
});
