import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Shop } from '@/types';

vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

vi.mock('@/components/store/StoreSearchCombobox', () => ({
  StoreSearchCombobox: () => <div data-testid="store-search" />,
}));

vi.mock('@/components/store/StoreSelectList', () => ({
  StoreSelectList: ({
    shops,
    onRemove,
    onToggleFavorite,
  }: {
    shops: Shop[];
    onRemove?: (id: string) => void;
    onToggleFavorite?: (id: string) => void;
  }) => (
    <div data-testid="store-list">
      {shops.length === 0 && <p>Aucune supérette disponible pour le moment.</p>}
      {shops.map((s) => (
        <div key={s.id} data-testid={`shop-item-${s.id}`}>
          <span>{s.name}</span>
          {onRemove && (
            <button onClick={() => onRemove(s.id)}>Retirer {s.name}</button>
          )}
          {onToggleFavorite && (
            <button onClick={() => onToggleFavorite(s.id)}>Favori {s.name}</button>
          )}
        </div>
      ))}
    </div>
  ),
}));

vi.mock('@/lib/services', () => ({
  listMyStores: vi.fn(),
  listShops: vi.fn(),
  removeStore: vi.fn(),
  toggleFavorite: vi.fn(),
  USE_MOCKS: false,
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import StoresPage from '@/app/(client)/stores/page';
import { listMyStores, listShops, removeStore, toggleFavorite } from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client Test' };

function mockAuth(user: typeof MOCK_USER | null, isLoading = false) {
  vi.mocked(useClientAuth).mockReturnValue({
    user,
    isLoading,
    login: vi.fn(),
    logout: vi.fn(),
  } as unknown as ReturnType<typeof useClientAuth>);
}

function makeShop(id: string, name: string, isFavorite = false): Shop {
  return {
    id,
    name,
    slug: id,
    city: 'Tunis',
    isActive: true,
    address: null,
    phone: null,
    isFavorite,
  };
}

const SHOP_A = makeShop('store-a', 'Aziza Menzah');
const SHOP_B = makeShop('store-b', 'Monoprix');
const SHOP_C = makeShop('store-c', 'Spar Lac');

describe('StoresPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAuth(MOCK_USER);
  });

  // --- Chargement initial ---

  it('affiche un squelette pendant le chargement', async () => {
    vi.mocked(listMyStores).mockReturnValue(new Promise(() => {}));
    render(<StoresPage />);
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  // --- Utilisateur connecté avec liste personnelle ---

  it('affiche la liste personnelle pour un utilisateur connecté', async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A, SHOP_B],
      total: 2,
      page: 1,
      limit: 20,
    });

    render(<StoresPage />);

    await waitFor(() => {
      expect(screen.getByText('Aziza Menzah')).toBeTruthy();
      expect(screen.getByText('Monoprix')).toBeTruthy();
    });
    expect(vi.mocked(listShops)).not.toHaveBeenCalled();
  });

  it('affiche les boutons Retirer et Favori pour la liste personnelle', async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(<StoresPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Retirer Aziza Menzah/i })).toBeTruthy();
      expect(screen.getByRole('button', { name: /Favori Aziza Menzah/i })).toBeTruthy();
    });
  });

  // --- Bouton "Afficher plus" ---

  it('affiche le bouton Afficher plus quand hasMore est vrai', async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A],
      total: 25,
      page: 1,
      limit: 20,
    });

    render(<StoresPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Afficher plus/i })).toBeTruthy();
    });
  });

  it("n'affiche pas Afficher plus quand tous les éléments sont chargés", async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A, SHOP_B],
      total: 2,
      page: 1,
      limit: 20,
    });

    render(<StoresPage />);

    await waitFor(() => screen.getByText('Aziza Menzah'));
    expect(screen.queryByText(/Afficher plus/i)).toBeNull();
  });

  it('Afficher plus charge la page suivante et accumule les résultats', async () => {
    vi.mocked(listMyStores)
      .mockResolvedValueOnce({ items: [SHOP_A, SHOP_B], total: 45, page: 1, limit: 20 })
      .mockResolvedValueOnce({ items: [SHOP_C], total: 45, page: 2, limit: 20 });

    render(<StoresPage />);

    await waitFor(() => {
      expect(screen.getByText('Aziza Menzah')).toBeTruthy();
      expect(screen.getByText('Monoprix')).toBeTruthy();
    });

    fireEvent.click(screen.getByRole('button', { name: /Afficher plus/i }));

    await waitFor(() => {
      expect(screen.getByText('Aziza Menzah')).toBeTruthy();
      expect(screen.getByText('Monoprix')).toBeTruthy();
      expect(screen.getByText('Spar Lac')).toBeTruthy();
    });

    expect(vi.mocked(listMyStores)).toHaveBeenCalledTimes(2);
    expect(vi.mocked(listMyStores)).toHaveBeenNthCalledWith(2, 2, 20);
  });

  // --- Utilisateur non connecté ---

  it('appelle listShops pour un utilisateur non connecté', async () => {
    mockAuth(null);
    vi.mocked(listShops).mockResolvedValue([SHOP_A, SHOP_B]);

    render(<StoresPage />);

    await waitFor(() => {
      expect(screen.getByText('Aziza Menzah')).toBeTruthy();
    });
    expect(vi.mocked(listMyStores)).not.toHaveBeenCalled();
    expect(vi.mocked(listShops)).toHaveBeenCalled();
  });

  it("n'affiche pas Retirer ni Favori pour un utilisateur non connecté", async () => {
    mockAuth(null);
    vi.mocked(listShops).mockResolvedValue([SHOP_A]);

    render(<StoresPage />);

    await waitFor(() => screen.getByText('Aziza Menzah'));
    expect(screen.queryByRole('button', { name: /Retirer/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /Favori/i })).toBeNull();
  });

  // --- Fallback public pour un utilisateur connecté sans supérettes ---

  it('utilise listShops quand la liste personnelle est vide', async () => {
    vi.mocked(listMyStores).mockResolvedValue({ items: [], total: 0, page: 1, limit: 20 });
    vi.mocked(listShops).mockResolvedValue([SHOP_A]);

    render(<StoresPage />);

    await waitFor(() => {
      expect(screen.getByText('Aziza Menzah')).toBeTruthy();
    });
    expect(vi.mocked(listShops)).toHaveBeenCalled();
  });

  // --- Actions ---

  it('supprime une supérette optimistement et appelle removeStore', async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A, SHOP_B],
      total: 2,
      page: 1,
      limit: 20,
    });
    vi.mocked(removeStore).mockResolvedValue(undefined);

    render(<StoresPage />);

    await waitFor(() => screen.getByText('Aziza Menzah'));

    fireEvent.click(screen.getByRole('button', { name: /Retirer Aziza Menzah/i }));

    await waitFor(() => {
      expect(screen.queryByTestId('shop-item-store-a')).toBeNull();
      expect(screen.getByText('Monoprix')).toBeTruthy();
    });
    expect(vi.mocked(removeStore)).toHaveBeenCalledWith('store-a');
  });

  it('recharge la page 1 si removeStore échoue', async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A],
      total: 1,
      page: 1,
      limit: 20,
    });
    vi.mocked(removeStore).mockRejectedValue(new Error('Network error'));

    render(<StoresPage />);
    await waitFor(() => screen.getByText('Aziza Menzah'));

    fireEvent.click(screen.getByRole('button', { name: /Retirer Aziza Menzah/i }));

    await waitFor(() => {
      expect(vi.mocked(listMyStores)).toHaveBeenCalledTimes(2);
    });
  });

  it('toggle favori appelle toggleFavorite', async () => {
    vi.mocked(listMyStores).mockResolvedValue({
      items: [SHOP_A],
      total: 1,
      page: 1,
      limit: 20,
    });
    vi.mocked(toggleFavorite).mockResolvedValue(undefined);

    render(<StoresPage />);
    await waitFor(() => screen.getByText('Aziza Menzah'));

    fireEvent.click(screen.getByRole('button', { name: /Favori Aziza Menzah/i }));

    await waitFor(() => {
      expect(vi.mocked(toggleFavorite)).toHaveBeenCalledWith('store-a', true);
    });
  });

  it('restaure le favori en cas d\'erreur sur toggleFavorite', async () => {
    const shopWithFav = makeShop('store-a', 'Aziza Menzah', true);
    vi.mocked(listMyStores).mockResolvedValue({
      items: [shopWithFav],
      total: 1,
      page: 1,
      limit: 20,
    });
    vi.mocked(toggleFavorite).mockRejectedValue(new Error('Network error'));

    render(<StoresPage />);
    await waitFor(() => screen.getByText('Aziza Menzah'));

    fireEvent.click(screen.getByRole('button', { name: /Favori Aziza Menzah/i }));

    await waitFor(() => {
      expect(vi.mocked(toggleFavorite)).toHaveBeenCalledWith('store-a', false);
    });
  });
});
