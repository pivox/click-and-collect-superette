import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CatalogPage from '@/app/(client)/stores/[shopId]/catalog/page';
import {
  createKadhia,
  getCurrentKadhia,
  getShop,
  listCatalog,
} from '@/lib/services';
import type { Kadhia, ProductOffer, Shop } from '@/types';

vi.mock('@/lib/services', () => ({
  activateKadhia: vi.fn(),
  addLine: vi.fn(),
  createKadhia: vi.fn(),
  getCurrentKadhia: vi.fn(),
  getShop: vi.fn(),
  listCatalog: vi.fn(),
}));

const SHOP = {
  id: 'store-1',
  name: 'Supérette El Amal',
  slug: 'superette-el-amal',
  address: null,
  city: 'Tunis',
  phone: null,
  isActive: true,
} satisfies Shop;

const EMPTY_KADHIA = {
  id: 'kadhia-1',
  shopId: 'store-1',
  status: 'draft' as const,
  lines: [],
  totalTnd: '0.000',
  orderId: null,
  notes: null,
} satisfies Kadhia;

function makeProduct(index: number): ProductOffer {
  return {
    id: `product-${index}`,
    productReferenceId: `ref-${index}`,
    nameFr: `Produit test ${index}`,
    nameAr: null,
    brand: 'Marque test',
    volume: 1,
    unit: 'piece',
    priceTnd: '1.000',
    isAvailable: true,
    photoUrl: null,
    category: 'test',
    categoryNameFr: 'Test',
  };
}

describe('CatalogPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listCatalog).mockResolvedValue({
      items: [],
      categories: [],
      page: 1,
      itemsPerPage: 30,
      total: 0,
      pages: 1,
    });
    vi.mocked(getCurrentKadhia).mockResolvedValue({ type: 'none' });
    vi.mocked(getShop).mockResolvedValue(SHOP);
  });

  it('désactive Commencer dans le HTML serveur avant hydratation', () => {
    const container = document.createElement('div');
    container.innerHTML = renderToString(
      <CatalogPage params={{ shopId: 'store-1' }} />,
    );

    const startButton = Array.from(container.querySelectorAll('button'))
      .find((button) => button.textContent?.includes('Préparation'));

    expect(startButton).toBeInstanceOf(HTMLButtonElement);
    expect(startButton).toBeDisabled();
  });

  it('permet de commencer une Kadhia après hydratation', async () => {
    vi.mocked(createKadhia).mockResolvedValue(EMPTY_KADHIA);
    render(<CatalogPage params={{ shopId: 'store-1' }} />);

    const startButton = await screen.findByRole('button', { name: 'Commencer' });
    expect(startButton).toBeEnabled();

    fireEvent.click(startButton);

    await waitFor(() => expect(createKadhia).toHaveBeenCalledWith('store-1'));
  });

  it('charge le catalogue page par page et permet d’afficher la suite', async () => {
    vi.mocked(listCatalog)
      .mockResolvedValueOnce({
        items: Array.from({ length: 30 }, (_, index) => makeProduct(index + 1)),
        categories: [{ key: 'test', labelFr: 'Test', labelAr: null }],
        page: 1,
        itemsPerPage: 30,
        total: 35,
        pages: 2,
      })
      .mockResolvedValueOnce({
        items: Array.from({ length: 5 }, (_, index) => makeProduct(index + 31)),
        categories: [{ key: 'test', labelFr: 'Test', labelAr: null }],
        page: 2,
        itemsPerPage: 30,
        total: 35,
        pages: 2,
      });

    render(<CatalogPage params={{ shopId: 'store-1' }} />);

    expect(await screen.findByText('Produit test 1')).toBeTruthy();
    expect(screen.getByText('Produit test 30')).toBeTruthy();
    expect(screen.queryByText('Produit test 31')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: /afficher 5 produits de plus/i }));

    await waitFor(() => expect(listCatalog).toHaveBeenCalledWith({
      shopId: 'store-1',
      category: 'all',
      search: '',
      page: 2,
      itemsPerPage: 30,
    }));
    expect(await screen.findByText('Produit test 35')).toBeTruthy();
  });
});
