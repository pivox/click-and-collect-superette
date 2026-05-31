import { fireEvent, render, screen, waitFor } from '@testing-library/react';
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
  confirmCustomerPickupSession: vi.fn(),
  getOrder: vi.fn(),
  getPickupSession: vi.fn(),
  USE_MOCKS: false,
  mockDelay: (v: unknown) => Promise.resolve(v),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import PickupQrPage from '@/app/(client)/orders/[orderId]/pickup/page';
import {
  confirmCustomerPickupSession,
  getOrder,
  getPickupSession,
} from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import type { OrderStatus } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client Test' };
const PICKUP_SESSION = {
  id: 'pickup-session-uuid-1',
  token: '11111111-1111-4111-8111-111111111111',
  expiresAt: '2026-05-29T10:00:00+01:00',
  isUsed: false,
  isExpired: false,
  qrPayload: '11111111-1111-4111-8111-111111111111',
};

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
    vi.mocked(getPickupSession).mockResolvedValue(PICKUP_SESSION);
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
    expect(getPickupSession).not.toHaveBeenCalled();
  });

  it('redirige via router.replace si le statut n\'est pas éligible au retrait', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('preparing'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/orders/order-uuid-1');
    });
    expect(getPickupSession).not.toHaveBeenCalled();
  });

  it('affiche le vrai QR code avec le token de retrait pour une commande ready', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('ready'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(screen.getByText(/Présente ce QR code au comptoir/i)).toBeTruthy();
    });
    expect(getPickupSession).toHaveBeenCalledWith('order-uuid-1');
    expect(
      screen.getByRole('img', {
        name: /QR code de retrait 11111111-1111-4111-8111-111111111111/i,
      }),
    ).toBeTruthy();
    expect(screen.getAllByText('11111111-1111-4111-8111-111111111111')).toHaveLength(1);
    expect(screen.getByText('CMD-ORDER1')).toBeTruthy();
    expect(screen.getByText('Supérette El Amen')).toBeTruthy();
    expect(screen.getByText('Rue de la Liberté, Tunis')).toBeTruthy();
  });

  it('affiche la confirmation client pour une commande pickup_pending', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('pickup_pending'));
    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);
    await waitFor(() => {
      expect(screen.getByText(/Retrait scanné par le marchand/i)).toBeTruthy();
    });
    expect(screen.getByRole('button', { name: /J'ai récupéré ma Kadhia/i })).toBeTruthy();
    expect(screen.queryByRole('img', { name: /QR code de retrait/i })).toBeNull();
  });

  it('confirme la réception client depuis pickup_pending', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('pickup_pending'));
    vi.mocked(confirmCustomerPickupSession).mockResolvedValue({
      id: 'pickup-session-uuid-1',
      orderId: 'order-uuid-1',
      orderStatus: 'pickup_pending',
      scannedAt: '2026-05-28T10:05:00+01:00',
      merchantConfirmedAt: null,
      customerConfirmedAt: '2026-05-28T10:06:00+01:00',
      isUsed: false,
      isCompleted: false,
    });

    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);

    const button = await screen.findByRole('button', {
      name: /J'ai récupéré ma Kadhia/i,
    });
    fireEvent.click(button);

    await waitFor(() => {
      expect(confirmCustomerPickupSession).toHaveBeenCalledWith(
        'pickup-session-uuid-1',
      );
    });
    expect(screen.getByText(/Confirmation client enregistrée/i)).toBeTruthy();
  });

  it('affiche un message clair si le QR code est expiré', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('ready'));
    vi.mocked(getPickupSession).mockResolvedValue({
      ...PICKUP_SESSION,
      isExpired: true,
    });

    render(<PickupQrPage params={{ orderId: 'order-uuid-1' }} />);

    await waitFor(() => {
      expect(screen.getByText(/QR code expiré/i)).toBeTruthy();
    });
    expect(screen.queryByRole('img', { name: /QR code de retrait/i })).toBeNull();
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
