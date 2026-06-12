import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CatalogPage from '@/app/(client)/stores/[shopId]/catalog/page';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';
import {
  addLine,
  getCurrentKadhia,
  getShop,
  getStoreSuggestions,
  listCatalog,
  listFavoriteProducts,
  toggleProductFavorite,
} from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import type { ProductOffer, Shop } from '@/types';

vi.mock('@/lib/services', () => ({
  activateKadhia: vi.fn(),
  addLine: vi.fn(),
  createKadhia: vi.fn(),
  getCurrentKadhia: vi.fn(),
  getShop: vi.fn(),
  getStoreSuggestions: vi.fn(),
  listCatalog: vi.fn(),
  listFavoriteProducts: vi.fn(),
  toggleProductFavorite: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client' };

const SHOP = {
  id: 'store-1',
  name: 'Supérette El Amal',
  slug: 'superette-el-amal',
  address: null,
  city: 'Tunis',
  phone: null,
  isActive: true,
} satisfies Shop;

function makeProduct(id: string, nameFr: string): ProductOffer {
  return {
    id,
    productReferenceId: `ref-${id}`,
    nameFr,
    nameAr: null,
    brand: 'Marque',
    volume: null,
    unit: null,
    priceTnd: '2.000',
    isAvailable: true,
    photoUrl: null,
    category: 'test',
  };
}

function renderPage() {
  return render(
    <ClientLocaleProvider>
      <CatalogPage params={{ shopId: 'store-1' }} />
    </ClientLocaleProvider>,
  );
}

describe('CatalogPage — suggestions et favoris', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    vi.mocked(useClientAuth).mockReturnValue({
      user: MOCK_USER,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      updateUser: vi.fn(),
    });
    vi.mocked(listCatalog).mockResolvedValue({
      items: [makeProduct('grid-1', 'Produit grille')],
      categories: [],
      page: 1,
      itemsPerPage: 30,
      total: 1,
      pages: 1,
    });
    vi.mocked(getCurrentKadhia).mockResolvedValue({ type: 'none' });
    vi.mocked(getShop).mockResolvedValue(SHOP);
    vi.mocked(getStoreSuggestions).mockResolvedValue({
      frequentlyBoughtTogether: [],
      recentlyOrdered: [],
    });
    vi.mocked(listFavoriteProducts).mockResolvedValue([]);
  });

  it('affiche les sections suggestions quand elles ont du contenu', async () => {
    vi.mocked(getStoreSuggestions).mockResolvedValue({
      frequentlyBoughtTogether: [makeProduct('sugg-1', 'Café Bondin')],
      recentlyOrdered: [makeProduct('sugg-2', 'Lait Vitalait')],
    });

    renderPage();

    expect(await screen.findByText('Souvent achetés ensemble')).toBeInTheDocument();
    expect(screen.getByText('Café Bondin')).toBeInTheDocument();
    expect(screen.getByText('Récemment commandés')).toBeInTheDocument();
    expect(screen.getByText('Lait Vitalait')).toBeInTheDocument();
  });

  it('masque les sections vides', async () => {
    renderPage();

    expect(await screen.findByText('Produit grille')).toBeInTheDocument();
    expect(screen.queryByText('Souvent achetés ensemble')).not.toBeInTheDocument();
    expect(screen.queryByText('Récemment commandés')).not.toBeInTheDocument();
    expect(screen.queryByText('Mes favoris')).not.toBeInTheDocument();
  });

  it('ne charge ni favoris ni suggestions pour un visiteur non connecté', async () => {
    vi.mocked(useClientAuth).mockReturnValue({
      user: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
      updateUser: vi.fn(),
    });

    renderPage();

    expect(await screen.findByText('Produit grille')).toBeInTheDocument();
    expect(getStoreSuggestions).not.toHaveBeenCalled();
    expect(listFavoriteProducts).not.toHaveBeenCalled();
  });

  it('affiche la section Mes favoris et bascule un favori avec mise à jour optimiste', async () => {
    vi.mocked(listFavoriteProducts).mockResolvedValue([makeProduct('fav-1', 'Harissa Sicam')]);
    vi.mocked(toggleProductFavorite).mockResolvedValue(undefined);

    renderPage();

    expect(await screen.findByText('Mes favoris')).toBeInTheDocument();
    expect(screen.getByText('Harissa Sicam')).toBeInTheDocument();

    // Retire le favori depuis l'étoile (aria-pressed=true dans la section favoris).
    const removeButton = screen.getByRole('button', { name: 'Retirer des favoris Harissa Sicam' });
    expect(removeButton).toHaveAttribute('aria-pressed', 'true');
    fireEvent.click(removeButton);

    await waitFor(() => {
      expect(toggleProductFavorite).toHaveBeenCalledWith('fav-1', false);
    });
    expect(screen.queryByText('Mes favoris')).not.toBeInTheDocument();
  });

  it('annule la mise à jour optimiste si le toggle échoue', async () => {
    vi.mocked(listFavoriteProducts).mockResolvedValue([makeProduct('fav-1', 'Harissa Sicam')]);
    vi.mocked(toggleProductFavorite).mockRejectedValue(new Error('boom'));

    renderPage();

    const removeButton = await screen.findByRole('button', {
      name: 'Retirer des favoris Harissa Sicam',
    });
    fireEvent.click(removeButton);

    expect(
      await screen.findByText('Impossible de mettre à jour le favori. Réessaie.'),
    ).toBeInTheDocument();
    // Le favori est restauré après rollback.
    expect(screen.getByText('Mes favoris')).toBeInTheDocument();
    expect(screen.getByText('Harissa Sicam')).toBeInTheDocument();
  });

  it('recharge les suggestions quand une ligne est ajoutée à la Kadhia', async () => {
    const emptyKadhia = {
      id: 'kadhia-1',
      shopId: 'store-1',
      status: 'draft' as const,
      lines: [],
      totalTnd: '0.000',
    };
    const gridProduct = makeProduct('grid-1', 'Produit grille');
    const kadhiaWithLine = {
      ...emptyKadhia,
      lines: [
        {
          id: 'grid-1',
          productOffer: gridProduct,
          quantity: 1,
          unitPriceTnd: '2.000',
          lineTotalTnd: '2.000',
        },
      ],
      totalTnd: '2.000',
    };
    vi.mocked(getCurrentKadhia).mockResolvedValue({ type: 'active', kadhia: emptyKadhia });
    vi.mocked(addLine).mockResolvedValue(kadhiaWithLine);

    renderPage();

    // Premier chargement avec la Kadhia vide.
    await waitFor(() => {
      expect(getStoreSuggestions).toHaveBeenCalledWith('store-1', 'kadhia-1');
    });
    const callsBeforeAdd = vi.mocked(getStoreSuggestions).mock.calls.length;

    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Produit grille' }));

    // L'ajout d'une ligne change le seed de co-occurrence → refetch.
    await waitFor(() => {
      expect(vi.mocked(getStoreSuggestions).mock.calls.length).toBeGreaterThan(callsBeforeAdd);
    });
  });

  it('marque un produit de la grille comme favori', async () => {
    vi.mocked(toggleProductFavorite).mockResolvedValue(undefined);

    renderPage();

    const addButton = await screen.findByRole('button', {
      name: 'Ajouter aux favoris Produit grille',
    });
    expect(addButton).toHaveAttribute('aria-pressed', 'false');
    fireEvent.click(addButton);

    await waitFor(() => {
      expect(toggleProductFavorite).toHaveBeenCalledWith('grid-1', true);
    });
    expect(await screen.findByText('Mes favoris')).toBeInTheDocument();
  });
});
