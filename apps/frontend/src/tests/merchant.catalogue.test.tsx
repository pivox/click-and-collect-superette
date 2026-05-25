import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantCatalogPage from '@/app/merchant/catalogue/page';
import { listMerchantCatalog } from '@/lib/services/merchant-catalog.service';
import type { MerchantCatalogProduct } from '@/lib/types/merchant-catalog.types';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-catalog.service', async () => {
  const actual = await vi.importActual<typeof import('@/lib/services/merchant-catalog.service')>(
    '@/lib/services/merchant-catalog.service',
  );

  return {
    ...actual,
    listMerchantCatalog: vi.fn(),
  };
});

const products: MerchantCatalogProduct[] = [
  {
    id: 'mp-1',
    product_reference_id: 'ref-1',
    name_fr: 'Lait demi-écrémé',
    brand: 'Vitalait',
    category: 'Boissons',
    merchant_category_name: 'Lait & produits laitiers',
    volume: '1',
    unit: 'litre',
    price_tnd: '1.650',
    is_available: true,
    is_visible: true,
    merchant_note: null,
  },
  {
    id: 'mp-2',
    product_reference_id: 'ref-2',
    name_fr: 'Couscous fin',
    brand: 'Rose Blanche',
    category: 'Epicerie',
    merchant_category_name: null,
    volume: '1',
    unit: 'kg',
    price_tnd: '2.400',
    is_available: false,
    is_visible: false,
    merchant_note: 'Rupture fournisseur',
  },
];

describe('MerchantCatalogPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchantCatalog).mockResolvedValue(products);
  });

  it('renders a merchant catalogue product', async () => {
    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByRole('heading', { name: 'Catalogue' })).toBeInTheDocument();
    expect(listMerchantCatalog).toHaveBeenCalledWith('store-1');
    expect(screen.getByText('Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.getByText('Vitalait')).toBeInTheDocument();
    expect(screen.getByText('Lait & produits laitiers')).toBeInTheDocument();
    expect(screen.getByText('1,650 TND')).toBeInTheDocument();
    expect(screen.getByText('Disponible')).toBeInTheDocument();
    expect(screen.getByText('Visible')).toBeInTheDocument();
  });

  it('filters merchant catalogue products locally after submit', async () => {
    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.getByText('Couscous fin')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Rechercher dans le catalogue'), {
      target: { value: 'lait' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    expect(screen.getByText('Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.queryByText('Couscous fin')).not.toBeInTheDocument();
    expect(listMerchantCatalog).toHaveBeenCalledTimes(1);
    expect(listMerchantCatalog).toHaveBeenCalledWith('store-1');
  });

  it('can retry after an error and render an empty catalogue', async () => {
    vi.mocked(listMerchantCatalog)
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce([]);

    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Impossible de charger le catalogue.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    expect(await screen.findByText('Aucun produit dans ce catalogue.')).toBeInTheDocument();
    await waitFor(() => expect(listMerchantCatalog).toHaveBeenCalledTimes(2));
  });
});
