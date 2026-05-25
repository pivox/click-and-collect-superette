import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantCatalogPage from '@/app/merchant/catalogue/page';
import {
  addMerchantCatalogProduct,
  bulkUpdateMerchantProductAvailability,
  createMerchantLocalProduct,
  listMerchantCatalog,
  searchMerchantProductReferences,
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
    addMerchantCatalogProduct: vi.fn(),
    bulkUpdateMerchantProductAvailability: vi.fn(),
    createMerchantLocalProduct: vi.fn(),
    listMerchantCatalog: vi.fn(),
    searchMerchantProductReferences: vi.fn(),
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
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((promiseResolve, promiseReject) => {
    resolve = promiseResolve;
    reject = promiseReject;
  });

  return { promise, resolve, reject };
}

function productReference(overrides: Partial<{
  id: string;
  name_fr: string;
  brand: string;
  category: string;
  volume: string | null;
  unit: string;
  already_in_catalog: boolean;
}> = {}) {
  const id = overrides.id ?? 'ref-1';

  return {
    id,
    name_fr: overrides.name_fr ?? 'Couscous fin',
    name_ar: null,
    brand_id: `brand-${id}`,
    brand: overrides.brand ?? 'Rose Blanche',
    category_id: `cat-${id}`,
    category: overrides.category ?? 'Epicerie',
    category_ar: null,
    category_slug: overrides.category?.toLowerCase() ?? 'epicerie',
    volume: overrides.volume ?? '1',
    unit: overrides.unit ?? 'kg',
    barcode: null,
    already_in_catalog: overrides.already_in_catalog ?? false,
  };
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
    vi.mocked(addMerchantCatalogProduct).mockResolvedValue(undefined);
    vi.mocked(createMerchantLocalProduct).mockResolvedValue({
      merchant_product_id: 'mp-local-1',
      local_product_id: 'local-1',
      name_fr: 'Harissa maison',
      name_ar: null,
      brand: null,
      category: 'Epicerie',
      volume: '350.000',
      unit: 'gramme',
      price_tnd: '4.500',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    });
    vi.mocked(listMerchantCatalog).mockResolvedValue(products);
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      limit: 20,
    });
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
    fireEvent.click(screen.getByLabelText('Visible'));
    fireEvent.change(screen.getByLabelText('Note marchand'), {
      target: { value: 'Rupture temporaire' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-1', {
        price_tnd: '1.700',
        is_available: false,
        is_visible: false,
        merchant_note: 'Rupture temporaire',
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('searches product references from the add product drawer', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));

    await waitFor(() =>
      expect(searchMerchantProductReferences).toHaveBeenCalledWith('store-1', {
        q: 'couscous',
        page: 1,
        limit: 20,
      }),
    );
  });

  it('marks already catalogued product references as unavailable for add', async () => {
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [
        {
          id: 'ref-1',
          name_fr: 'Couscous fin',
          name_ar: null,
          brand_id: 'brand-1',
          brand: 'Rose Blanche',
          category_id: 'cat-1',
          category: 'Epicerie',
          category_ar: null,
          category_slug: 'epicerie',
          volume: '1',
          unit: 'kg',
          barcode: null,
          already_in_catalog: true,
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));

    expect(await screen.findByText('Déjà dans mon catalogue')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ajouter Couscous fin' })).toBeDisabled();
  });

  it('adds a selected product reference to the merchant catalogue', async () => {
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [
        {
          id: 'ref-1',
          name_fr: 'Couscous fin',
          name_ar: null,
          brand_id: 'brand-1',
          brand: 'Rose Blanche',
          category_id: 'cat-1',
          category: 'Epicerie',
          category_ar: null,
          category_slug: 'epicerie',
          volume: '1',
          unit: 'kg',
          barcode: null,
          already_in_catalog: false,
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Couscous fin' }));

    expect(screen.getByText('Catégorie référentiel : Epicerie')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '2.400' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter à mon catalogue' }));

    await waitFor(() =>
      expect(addMerchantCatalogProduct).toHaveBeenCalledWith('store-1', {
        product_reference_id: 'ref-1',
        price_tnd: '2.400',
        is_available: true,
        is_visible: true,
        merchant_note: null,
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('creates a local product in the merchant catalogue', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Produit local' }));

    expect(screen.getByRole('dialog', { name: 'Créer un produit local' })).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Nom en français'), {
      target: { value: 'Harissa maison' },
    });
    fireEvent.change(screen.getByLabelText('Catégorie marchand'), {
      target: { value: 'Epicerie' },
    });
    fireEvent.change(screen.getByLabelText('Volume'), { target: { value: '350' } });
    fireEvent.change(screen.getByLabelText('Unité'), { target: { value: 'gramme' } });
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '4,5' } });
    fireEvent.click(screen.getByRole('button', { name: 'Créer dans mon catalogue' }));

    await waitFor(() =>
      expect(createMerchantLocalProduct).toHaveBeenCalledWith('store-1', {
        name_fr: 'Harissa maison',
        name_ar: null,
        brand_name: null,
        volume: '350.000',
        unit: 'gramme',
        barcode: null,
        default_category_name: 'Epicerie',
        price_tnd: '4.500',
        is_available: true,
        is_visible: true,
        merchant_note: null,
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('rejects blank local product names without creating it', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Produit local' }));
    fireEvent.change(screen.getByLabelText('Nom en français'), { target: { value: '   ' } });
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '4.500' } });
    fireEvent.click(screen.getByRole('button', { name: 'Créer dans mon catalogue' }));

    expect(screen.getByRole('alert')).toHaveTextContent('Le nom en français est obligatoire.');
    expect(createMerchantLocalProduct).not.toHaveBeenCalled();
  });

  it('rejects invalid local product prices without creating it', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Produit local' }));
    fireEvent.change(screen.getByLabelText('Nom en français'), {
      target: { value: 'Harissa maison' },
    });
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '0' } });
    fireEvent.click(screen.getByRole('button', { name: 'Créer dans mon catalogue' }));

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Le prix doit être supérieur à 0 avec au maximum 3 décimales.',
    );
    expect(createMerchantLocalProduct).not.toHaveBeenCalled();
  });

  it('keeps the local product drawer open while creation is submitting', async () => {
    const pendingCreation = deferred<Awaited<ReturnType<typeof createMerchantLocalProduct>>>();
    vi.mocked(createMerchantLocalProduct).mockReturnValue(pendingCreation.promise);

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Produit local' }));
    fireEvent.change(screen.getByLabelText('Nom en français'), {
      target: { value: 'Harissa maison' },
    });
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '4.500' } });
    fireEvent.click(screen.getByRole('button', { name: 'Créer dans mon catalogue' }));

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.getByRole('dialog', { name: 'Créer un produit local' })).toBeInTheDocument();

    pendingCreation.resolve({
      merchant_product_id: 'mp-local-1',
      local_product_id: 'local-1',
      name_fr: 'Harissa maison',
      name_ar: null,
      brand: null,
      category: 'Epicerie',
      volume: null,
      unit: 'piece',
      price_tnd: '4.500',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    });

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('normalizes comma decimal prices when adding a selected product reference', async () => {
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [productReference({ id: 'ref-1', name_fr: 'Couscous fin' })],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Couscous fin' }));
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '2,4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter à mon catalogue' }));

    await waitFor(() =>
      expect(addMerchantCatalogProduct).toHaveBeenCalledWith('store-1', {
        product_reference_id: 'ref-1',
        price_tnd: '2.400',
        is_available: true,
        is_visible: true,
        merchant_note: null,
      }),
    );
  });

  it('keeps the latest product reference search results when an older search resolves later', async () => {
    const olderSearch = deferred<Awaited<ReturnType<typeof searchMerchantProductReferences>>>();
    const latestSearch = deferred<Awaited<ReturnType<typeof searchMerchantProductReferences>>>();
    vi.mocked(searchMerchantProductReferences)
      .mockReturnValueOnce(olderSearch.promise)
      .mockReturnValueOnce(latestSearch.promise);

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));

    const searchInput = screen.getByLabelText('Rechercher dans le référentiel');
    const searchForm = searchInput.closest('form');
    if (!searchForm) throw new Error('Search form missing');

    fireEvent.change(searchInput, { target: { value: 'couscous' } });
    fireEvent.submit(searchForm);
    fireEvent.change(searchInput, { target: { value: 'lait' } });
    fireEvent.submit(searchForm);

    latestSearch.resolve({
      items: [productReference({ id: 'ref-lait', name_fr: 'Lait entier', brand: 'Vitalait' })],
      total: 1,
      page: 1,
      limit: 20,
    });

    expect(await screen.findByText('Lait entier')).toBeInTheDocument();

    olderSearch.resolve({
      items: [productReference({ id: 'ref-old', name_fr: 'Semoule ancienne' })],
      total: 1,
      page: 1,
      limit: 20,
    });

    await waitFor(() => expect(screen.getByText('Lait entier')).toBeInTheDocument());
    expect(screen.queryByText('Semoule ancienne')).not.toBeInTheDocument();
  });

  it('does not reload the catalogue when an obsolete add resolves after the drawer is closed', async () => {
    const pendingAdd = deferred<Awaited<ReturnType<typeof addMerchantCatalogProduct>>>();
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [productReference({ id: 'ref-1', name_fr: 'Couscous fin' })],
      total: 1,
      page: 1,
      limit: 20,
    });
    vi.mocked(addMerchantCatalogProduct).mockReturnValue(pendingAdd.promise);

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Couscous fin' }));
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '2.400' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter à mon catalogue' }));

    fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    pendingAdd.resolve(undefined);

    await waitFor(() => expect(addMerchantCatalogProduct).toHaveBeenCalledTimes(1));
    expect(listMerchantCatalog).toHaveBeenCalledTimes(1);
  });

  it('rejects an invalid add price without adding the product reference', async () => {
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [
        {
          id: 'ref-1',
          name_fr: 'Couscous fin',
          name_ar: null,
          brand_id: 'brand-1',
          brand: 'Rose Blanche',
          category_id: 'cat-1',
          category: 'Epicerie',
          category_ar: null,
          category_slug: 'epicerie',
          volume: '1',
          unit: 'kg',
          barcode: null,
          already_in_catalog: false,
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Depuis référentiel' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Couscous fin' }));
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '2.4009' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter à mon catalogue' }));

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Le prix doit être supérieur à 0 avec au maximum 3 décimales.',
    );
    expect(addMerchantCatalogProduct).not.toHaveBeenCalled();
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

  it('keeps tab focus inside the edit drawer', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);

    const dialog = screen.getByRole('dialog', { name: 'Modifier Lait demi-écrémé' });
    const priceInput = screen.getByLabelText('Prix TND');
    const saveButton = screen.getByRole('button', { name: 'Enregistrer' });

    expect(priceInput).toHaveFocus();

    saveButton.focus();
    fireEvent.keyDown(dialog, { key: 'Tab' });

    expect(priceInput).toHaveFocus();

    fireEvent.keyDown(dialog, { key: 'Tab', shiftKey: true });

    expect(saveButton).toHaveFocus();
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
    expect(screen.getByText('2 produits mis à jour.')).toBeInTheDocument();
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
