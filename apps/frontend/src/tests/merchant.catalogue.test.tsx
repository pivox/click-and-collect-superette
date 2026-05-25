import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantCatalogPage from '@/app/merchant/catalogue/page';
import {
  bulkUpdateMerchantProductAvailability,
  listMerchantCatalog,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';
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
    bulkUpdateMerchantProductAvailability: vi.fn(),
    listMerchantCatalog: vi.fn(),
    updateMerchantCatalogProduct: vi.fn(),
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
    vi.mocked(bulkUpdateMerchantProductAvailability).mockResolvedValue({
      updated_count: 2,
      is_available: false,
      merchant_note: 'Rupture temporaire',
      merchant_product_ids: ['mp-1', 'mp-2'],
    });
    vi.mocked(listMerchantCatalog).mockResolvedValue(products);
    vi.mocked(updateMerchantCatalogProduct).mockResolvedValue(undefined);
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
      expect(button).not.toBeDisabled();
    });
  });

  it('edits a merchant catalogue product from the drawer', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);

    expect(screen.getByRole('dialog', { name: 'Modifier Lait demi-écrémé' })).toBeInTheDocument();
    expect(screen.getByText('Catégorie : Lait & produits laitiers')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '1.700' } });
    fireEvent.click(screen.getByLabelText('Disponible'));
    fireEvent.change(screen.getByLabelText('Note marchand'), {
      target: { value: 'Rupture temporaire' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-1', {
        price_tnd: '1.700',
        is_available: false,
        is_visible: true,
        merchant_note: 'Rupture temporaire',
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('rejects a price with more than 3 decimals without updating the product', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);

    const priceInput = screen.getByLabelText('Prix TND');
    fireEvent.change(priceInput, { target: { value: '1.2345' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Le prix doit être supérieur à 0 avec au maximum 3 décimales.',
    );
    expect(priceInput).toHaveAttribute('aria-invalid', 'true');
    expect(updateMerchantCatalogProduct).not.toHaveBeenCalled();
  });

  it('closes the edit drawer with Escape', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);

    expect(screen.getByRole('dialog', { name: 'Modifier Lait demi-écrémé' })).toBeInTheDocument();

    fireEvent.keyDown(document, { key: 'Escape' });

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('limits bulk selection to 50 merchant products', async () => {
    const manyProducts = Array.from({ length: 51 }, (_, index) => ({
      ...products[0],
      id: `mp-${index + 1}`,
      product_reference_id: `ref-${index + 1}`,
      name_fr: `Produit ${index + 1}`,
    }));
    vi.mocked(listMerchantCatalog).mockResolvedValue(manyProducts);

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Produit 1');
    fireEvent.click(screen.getByRole('button', { name: 'Mode sélection' }));

    for (const checkbox of screen.getAllByRole('checkbox', { name: /Sélectionner Produit/ })) {
      fireEvent.click(checkbox);
    }

    expect(screen.getByText('La sélection est limitée à 50 produits.')).toBeInTheDocument();
  });

  it('clears bulk selection when filters hide selected products', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Mode sélection' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sélectionner Couscous fin' }));

    expect(screen.getByText('1 produit sélectionné')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Rechercher dans le catalogue'), {
      target: { value: 'lait' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    expect(screen.getByText('0 produit sélectionné')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Marquer indisponible' })).toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'Marquer indisponible' }));

    expect(bulkUpdateMerchantProductAvailability).not.toHaveBeenCalled();
  });

  it('marks selected merchant products unavailable in bulk', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Mode sélection' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sélectionner Lait demi-écrémé' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sélectionner Couscous fin' }));
    fireEvent.click(screen.getByRole('button', { name: 'Marquer indisponible' }));

    await waitFor(() =>
      expect(bulkUpdateMerchantProductAvailability).toHaveBeenCalledWith('store-1', {
        merchant_product_ids: ['mp-1', 'mp-2'],
        is_available: false,
        merchant_note: 'Rupture temporaire',
      }),
    );
    await waitFor(() =>
      expect(screen.queryByRole('checkbox', { name: 'Sélectionner Lait demi-écrémé' })).not.toBeInTheDocument(),
    );
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('disables selection checkboxes while a bulk update is submitting', async () => {
    const pendingBulk = deferred<Awaited<ReturnType<typeof bulkUpdateMerchantProductAvailability>>>();
    vi.mocked(bulkUpdateMerchantProductAvailability).mockReturnValue(pendingBulk.promise);

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Mode sélection' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sélectionner Lait demi-écrémé' }));
    fireEvent.click(screen.getByRole('button', { name: 'Marquer indisponible' }));

    expect(screen.getByRole('checkbox', { name: 'Sélectionner Lait demi-écrémé' })).toBeDisabled();

    pendingBulk.resolve({
      updated_count: 1,
      is_available: false,
      merchant_note: 'Rupture temporaire',
      merchant_product_ids: ['mp-1'],
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
