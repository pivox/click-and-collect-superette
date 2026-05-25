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

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((promiseResolve) => {
    resolve = promiseResolve;
  });

  return { promise, resolve };
}

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
    const editButtons = screen.getAllByRole('button', { name: 'Modifier' });
    expect(editButtons).toHaveLength(2);
    editButtons.forEach((button) => {
      expect(button).toBeDisabled();
      expect(button).toHaveAttribute('title', 'Prévu dans une prochaine étape');
    });
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

  it('renders a dedicated empty state when filters hide all products', async () => {
    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Rechercher dans le catalogue'), {
      target: { value: 'introuvable' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    expect(screen.getByText('Aucun produit ne correspond aux filtres.')).toBeInTheDocument();
    expect(screen.queryByText('Aucun produit dans ce catalogue.')).not.toBeInTheDocument();
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

  it('disables retry while the catalogue is loading', async () => {
    const pendingCatalog = deferred<MerchantCatalogProduct[]>();
    vi.mocked(listMerchantCatalog).mockReturnValue(pendingCatalog.promise);

    render(React.createElement(MerchantCatalogPage));

    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeDisabled();

    pendingCatalog.resolve(products);

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Réessayer' })).not.toBeDisabled(),
    );
  });
});
