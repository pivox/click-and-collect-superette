import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

vi.mock('@/lib/services', () => ({
  listOrders: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import OrdersListPage from '@/app/(client)/orders/page';
import { listOrders } from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import type { Order } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client Test' };

function mockAuth(user: typeof MOCK_USER | null, isLoading = false) {
  vi.mocked(useClientAuth).mockReturnValue({
    user,
    isLoading,
    login: vi.fn(),
    logout: vi.fn(),
  } as unknown as ReturnType<typeof useClientAuth>);
}

function makeOrder(id: string, overrides: Partial<Order> = {}): Order {
  return {
    id,
    shopId: 'store-1',
    shopName: 'Épicerie Test',
    status: 'submitted',
    totalAmountTnd: '10.000',
    code: `#000${id}`,
    pickupSlot: null,
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectionReason: null,
    customerNote: null,
    lines: [],
    ...overrides,
  };
}

const emptyResult = { items: [], total: 0, page: 1, limit: 10 };

describe('OrdersListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAuth(MOCK_USER);
  });

  it("affiche un lien de connexion si l'utilisateur n'est pas authentifié", () => {
    mockAuth(null);
    vi.mocked(listOrders).mockResolvedValue(emptyResult);
    render(<OrdersListPage />);
    expect(screen.getByText(/Connecte-toi/i)).toBeTruthy();
    expect(screen.queryByText(/Aucune commande/i)).toBeNull();
  });

  it("retourne null pendant le chargement de l'auth", () => {
    mockAuth(null, true);
    vi.mocked(listOrders).mockResolvedValue(emptyResult);
    const { container } = render(<OrdersListPage />);
    expect(container.firstChild).toBeNull();
  });

  it("affiche l'état vide quand aucune commande", async () => {
    vi.mocked(listOrders).mockResolvedValue(emptyResult);
    render(<OrdersListPage />);
    await waitFor(() => {
      expect(screen.getByText(/Aucune commande pour le moment/i)).toBeTruthy();
    });
  });

  it('affiche la liste des commandes avec code et nom de supérette', async () => {
    vi.mocked(listOrders).mockResolvedValue({
      items: [
        makeOrder('1', { code: '#0001', shopName: 'Aziza Menzah' }),
        makeOrder('2', { code: '#0002', shopName: 'Monoprix' }),
      ],
      total: 2,
      page: 1,
      limit: 10,
    });
    render(<OrdersListPage />);
    await waitFor(() => {
      expect(screen.getByText('#0001')).toBeTruthy();
      expect(screen.getByText('#0002')).toBeTruthy();
      expect(screen.getByText('Aziza Menzah')).toBeTruthy();
    });
  });

  it('chaque commande est un lien vers la page de détail', async () => {
    vi.mocked(listOrders).mockResolvedValue({
      items: [makeOrder('order-uuid-1')],
      total: 1,
      page: 1,
      limit: 10,
    });
    render(<OrdersListPage />);
    await waitFor(() => screen.getByText('#000order-uuid-1'));
    const link = screen.getByRole('link', { name: /#000order-uuid-1/i });
    expect(link.getAttribute('href')).toBe('/orders/order-uuid-1');
  });

  it("n'affiche pas la pagination quand total <= limit", async () => {
    vi.mocked(listOrders).mockResolvedValue({
      items: [makeOrder('1')],
      total: 5,
      page: 1,
      limit: 10,
    });
    render(<OrdersListPage />);
    await waitFor(() => screen.getByText('#0001'));
    expect(screen.queryByText(/Page 1/i)).toBeNull();
  });

  it('affiche la pagination quand total > limit', async () => {
    vi.mocked(listOrders).mockResolvedValue({
      items: [makeOrder('1')],
      total: 25,
      page: 1,
      limit: 10,
    });
    render(<OrdersListPage />);
    await waitFor(() => {
      expect(screen.getByText(/Page 1 \/ 3/i)).toBeTruthy();
      expect(screen.getByText(/Suivant/i)).toBeTruthy();
    });
  });

  it('clique sur Suivant charge la page 2', async () => {
    vi.mocked(listOrders)
      .mockResolvedValueOnce({ items: [makeOrder('1')], total: 25, page: 1, limit: 10 })
      .mockResolvedValueOnce({ items: [makeOrder('11')], total: 25, page: 2, limit: 10 });

    render(<OrdersListPage />);
    await waitFor(() => screen.getByText(/Page 1 \/ 3/i));

    fireEvent.click(screen.getByText(/Suivant/i));

    await waitFor(() => {
      expect(vi.mocked(listOrders)).toHaveBeenCalledWith(2, 10);
    });
  });

  it("affiche un message d'erreur et un bouton Réessayer en cas d'échec", async () => {
    vi.mocked(listOrders).mockRejectedValue(new Error('Network error'));
    render(<OrdersListPage />);
    await waitFor(() => {
      expect(screen.getByText(/Impossible de charger les commandes/i)).toBeTruthy();
      expect(screen.getByRole('button', { name: /Réessayer/i })).toBeTruthy();
    });
  });

  it('appelle listOrders avec page=1 et limit=10 au chargement initial', async () => {
    vi.mocked(listOrders).mockResolvedValue(emptyResult);
    render(<OrdersListPage />);
    await waitFor(() => {
      expect(vi.mocked(listOrders)).toHaveBeenCalledWith(1, 10);
    });
  });

  it('indique "Action requise" pour les commandes partiellement acceptées', async () => {
    vi.mocked(listOrders).mockResolvedValue({
      items: [makeOrder('1', { status: 'partially_accepted' })],
      total: 1,
      page: 1,
      limit: 10,
    });
    render(<OrdersListPage />);
    await waitFor(() => {
      expect(screen.getByText(/Action requise/i)).toBeTruthy();
    });
  });
});
