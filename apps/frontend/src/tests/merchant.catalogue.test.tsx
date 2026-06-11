import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantCatalogPage from '@/app/merchant/catalogue/page';
import {
  addMerchantCatalogProduct,
  buildMerchantCatalogCsvTemplate,
  bulkUpdateMerchantProductAvailability,
  commitMerchantCatalogPhotoImport,
  createMerchantCategory,
  createMerchantLocalProduct,
  importMerchantCatalogCsv,
  listMerchantCategories,
  listMerchantCatalog,
  previewMerchantCatalogPhotoImport,
  searchMerchantProductReferences,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogListResult,
  MerchantCatalogProduct,
  MerchantCategory,
} from '@/lib/types/merchant-catalog.types';

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
    buildMerchantCatalogCsvTemplate: vi.fn(),
    bulkUpdateMerchantProductAvailability: vi.fn(),
    commitMerchantCatalogPhotoImport: vi.fn(),
    createMerchantCategory: vi.fn(),
    createMerchantLocalProduct: vi.fn(),
    importMerchantCatalogCsv: vi.fn(),
    listMerchantCategories: vi.fn(),
    listMerchantCatalog: vi.fn(),
    previewMerchantCatalogPhotoImport: vi.fn(),
    searchMerchantProductReferences: vi.fn(),
    updateMerchantCatalogProduct: vi.fn(),
  };
});

function catalogResult(items: MerchantCatalogProduct[]): MerchantCatalogListResult {
  return { items, total: items.length, page: 1, limit: 50, pages: 1 };
}

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
    requires_price_completion: false,
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
    requires_price_completion: false,
    merchant_note: 'Rupture fournisseur',
  },
];

const productToComplete: MerchantCatalogProduct = {
  id: 'mp-3',
  product_reference_id: 'ref-3',
  name_fr: 'Semoule fine',
  brand: 'Rose Blanche',
  category: 'Epicerie',
  merchant_category_name: null,
  volume: '1',
  unit: 'kg',
  price_tnd: '0.000',
  is_available: true,
  is_visible: false,
  requires_price_completion: true,
  merchant_note: null,
};

const activePromoProduct = {
  ...products[0],
  id: 'mp-promo',
  name_fr: 'Lait en promo',
  price_tnd: '2.500',
  promotion_price_tnd: '1.900',
  promotion_ends_on: '2026-06-30',
  promotion_active: true,
  effective_price_tnd: '1.900',
} as MerchantCatalogProduct;

const merchantCategories: MerchantCategory[] = [
  {
    id: 'merchant-cat-1',
    name_fr: 'Produits frais',
    name_ar: null,
    slug: 'produits-frais',
    parent_id: null,
    sort_order: 10,
    active: true,
    created_at: '2026-05-25T08:00:00+00:00',
    updated_at: '2026-05-25T08:00:00+00:00',
  },
  {
    id: 'merchant-cat-2',
    name_fr: 'Catégorie archivée',
    name_ar: null,
    slug: 'categorie-archivee',
    parent_id: null,
    sort_order: 20,
    active: false,
    created_at: '2026-05-25T08:00:00+00:00',
    updated_at: '2026-05-25T08:00:00+00:00',
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

const originalMediaDevices = navigator.mediaDevices;
const originalBarcodeDetector = (window as Window & { BarcodeDetector?: unknown }).BarcodeDetector;

describe('MerchantCatalogPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: originalMediaDevices,
    });
    if (originalBarcodeDetector === undefined) {
      delete (window as Window & { BarcodeDetector?: unknown }).BarcodeDetector;
    } else {
      Object.defineProperty(window, 'BarcodeDetector', {
        configurable: true,
        value: originalBarcodeDetector,
      });
    }
  });

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(bulkUpdateMerchantProductAvailability).mockResolvedValue({
      updated_count: 2,
      is_available: false,
      merchant_note: 'Rupture temporaire',
      merchant_product_ids: ['mp-1', 'mp-2'],
    });
    vi.mocked(commitMerchantCatalogPhotoImport).mockResolvedValue({
      id: 'store-1',
      created: 2,
      updated: 0,
      ignored: 0,
      items: [
        {
          line: 1,
          status: 'created',
          merchant_product_id: 'mp-photo-1',
          product_reference_id: 'ref-photo-1',
          local_product_id: null,
          name_fr: 'Lait demi-écrémé',
        },
        {
          line: 2,
          status: 'created',
          merchant_product_id: 'mp-photo-2',
          product_reference_id: null,
          local_product_id: 'local-photo-2',
          name_fr: 'Harissa maison',
        },
      ],
      errors: [],
    });
    vi.mocked(addMerchantCatalogProduct).mockResolvedValue(undefined);
    vi.mocked(buildMerchantCatalogCsvTemplate).mockReturnValue('name_fr,brand,volume,unit,price_tnd,is_available,is_visible\n');
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
      pack_quantity: 1,
    });
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult(products));
    vi.mocked(listMerchantCategories).mockResolvedValue(merchantCategories);
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      limit: 20,
    });
    vi.mocked(createMerchantCategory).mockResolvedValue({
      id: 'merchant-cat-3',
      name_fr: 'Petit déjeuner',
      name_ar: null,
      slug: 'petit-dejeuner',
      parent_id: null,
      sort_order: 30,
      active: true,
      created_at: '2026-05-25T08:00:00+00:00',
      updated_at: '2026-05-25T08:00:00+00:00',
    });
    vi.mocked(importMerchantCatalogCsv).mockResolvedValue({
      id: 'store-1',
      created: 1,
      updated: 1,
      ignored: 0,
      items: [
        {
          line: 2,
          status: 'created',
          merchant_product_id: 'mp-3',
          product_reference_id: 'ref-3',
          local_product_id: null,
          name_fr: 'Lait demi-écrémé',
        },
      ],
      errors: [
        {
          line: 4,
          code: 'PRICE_REQUIRED',
          field: 'price_tnd',
          message: 'Le prix est obligatoire.',
        },
      ],
    });
    vi.mocked(previewMerchantCatalogPhotoImport).mockResolvedValue({
      id: 'store-1',
      source_type: 'receipt',
      detected_count: 2,
      matched_reference_count: 1,
      local_candidate_count: 1,
      items: [
        {
          line: 1,
          status: 'matched_reference',
          product_reference_id: 'ref-photo-1',
          name_fr: 'Lait demi-écrémé',
          brand: 'Vitalait',
          volume: '1.000',
          unit: 'litre',
          barcode: '6191234567890',
          suggested_price_tnd: '1.650',
          confidence: '0.940',
          already_in_catalog: false,
        },
        {
          line: 2,
          status: 'local_candidate',
          product_reference_id: null,
          name_fr: 'Harissa maison',
          brand: 'Jouda',
          volume: '350.000',
          unit: 'gramme',
          barcode: null,
          suggested_price_tnd: '4.500',
          confidence: '0.820',
          already_in_catalog: false,
        },
      ],
    });
    vi.mocked(updateMerchantCatalogProduct).mockResolvedValue(undefined);
  });

  it('renders a merchant catalogue product', async () => {
    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByRole('heading', { name: 'Catalogue' })).toBeInTheDocument();
    expect(listMerchantCatalog).toHaveBeenCalledWith('store-1', expect.objectContaining({ page: 1 }));
    expect(listMerchantCategories).toHaveBeenCalledWith('store-1');
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

  it('filters products that need price completion', async () => {
    vi.mocked(listMerchantCatalog)
      .mockResolvedValueOnce(catalogResult(products))
      .mockResolvedValueOnce(catalogResult([productToComplete]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Produits à compléter' }));

    await waitFor(() =>
      expect(listMerchantCatalog).toHaveBeenLastCalledWith(
        'store-1',
        expect.objectContaining({ completion: 'needs_price', page: 1 }),
      ),
    );
    expect(await screen.findByText('Semoule fine')).toBeInTheDocument();
    expect(screen.getByText('À compléter')).toBeInTheDocument();
  });

  it('filters active promotions from the merchant catalogue', async () => {
    vi.mocked(listMerchantCatalog)
      .mockResolvedValueOnce(catalogResult(products))
      .mockResolvedValueOnce(catalogResult([activePromoProduct]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'En promo' }));
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    await waitFor(() =>
      expect(listMerchantCatalog).toHaveBeenLastCalledWith(
        'store-1',
        expect.objectContaining({ promotion: 'active', page: 1 }),
      ),
    );
    expect(await screen.findByText('Lait en promo')).toBeInTheDocument();
  });

  it('clears the products to complete filter', async () => {
    vi.mocked(listMerchantCatalog)
      .mockResolvedValueOnce(catalogResult(products))
      .mockResolvedValueOnce(catalogResult([productToComplete]))
      .mockResolvedValueOnce(catalogResult(products));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Produits à compléter' }));
    await screen.findByText('Semoule fine');
    fireEvent.click(screen.getByRole('button', { name: 'Afficher tout le catalogue' }));

    await waitFor(() =>
      expect(listMerchantCatalog).toHaveBeenLastCalledWith(
        'store-1',
        expect.objectContaining({ completion: 'all', page: 1 }),
      ),
    );
    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();
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
        merchant_category_id: null,
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('sets a promotion from the edit drawer and marks the row as promoted', async () => {
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult([activePromoProduct]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait en promo');
    expect(screen.getAllByText('En promo').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText('1,900 TND')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));

    fireEvent.change(screen.getByLabelText('Prix promo TND'), { target: { value: '1.700' } });
    fireEvent.change(screen.getByLabelText('Fin promo'), { target: { value: '2026-07-15' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith(
        'mp-promo',
        expect.objectContaining({
          promotion_price_tnd: '1.700',
          promotion_ends_on: '2026-07-15',
        }),
      ),
    );
  });

  it('clears a promotion from the edit drawer', async () => {
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult([activePromoProduct]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait en promo');
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    fireEvent.click(screen.getByRole('button', { name: 'Supprimer la promo' }));
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith(
        'mp-promo',
        expect.objectContaining({
          promotion_price_tnd: null,
          promotion_ends_on: null,
        }),
      ),
    );
  });

  it('keeps an incomplete imported product hidden until a positive price is entered', async () => {
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult([productToComplete]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Semoule fine');
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));

    const visibleCheckbox = screen.getByLabelText('Visible');
    expect(visibleCheckbox).toBeDisabled();
    expect(
      screen.getByText('Saisis un prix supérieur à 0 pour rendre ce produit visible.'),
    ).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '1.900' } });
    expect(visibleCheckbox).toBeEnabled();
    fireEvent.click(visibleCheckbox);
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-3', {
        price_tnd: '1.900',
        is_available: true,
        is_visible: true,
        merchant_note: null,
        merchant_category_id: null,
      }),
    );
  });

  it('saves non-price edits on hidden products that still need price completion', async () => {
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult([productToComplete]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Semoule fine');
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    fireEvent.click(screen.getByLabelText('Disponible'));
    fireEvent.change(screen.getByLabelText('Note marchand'), {
      target: { value: 'Prix à confirmer fournisseur' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-3', {
        is_available: false,
        is_visible: false,
        merchant_note: 'Prix à confirmer fournisseur',
        merchant_category_id: null,
      }),
    );
  });

  it('loads merchant categories and shows the selector in edit drawer', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);

    expect(screen.getByLabelText('Catégorie marchand')).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Catégorie par défaut : Boissons' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Produits frais' })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: 'Catégorie archivée' })).not.toBeInTheDocument();
  });

  it('updates a merchant product with the selected merchant category', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);
    fireEvent.change(screen.getByLabelText('Catégorie marchand'), {
      target: { value: 'merchant-cat-1' },
    });
    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '1.700' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-1', {
        price_tnd: '1.700',
        is_available: true,
        is_visible: true,
        merchant_note: null,
        merchant_category_id: 'merchant-cat-1',
      }),
    );
  });

  it('updates a merchant product with null category when default category is selected', async () => {
    const productWithMerchantCategory = {
      ...products[0],
      merchant_category_id: 'merchant-cat-1',
      merchant_category_name: 'Produits frais',
    };
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult([productWithMerchantCategory]));

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    fireEvent.change(screen.getByLabelText('Catégorie marchand'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-1', {
        price_tnd: '1.650',
        is_available: true,
        is_visible: true,
        merchant_note: null,
        merchant_category_id: null,
      }),
    );
  });

  it('creates a merchant category inline from the edit drawer and selects it', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);
    fireEvent.change(screen.getByLabelText('Nouvelle catégorie marchand'), {
      target: { value: 'Petit déjeuner' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Créer la catégorie' }));

    await waitFor(() =>
      expect(createMerchantCategory).toHaveBeenCalledWith('store-1', {
        name_fr: 'Petit déjeuner',
        name_ar: null,
        active: true,
      }),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() =>
      expect(updateMerchantCatalogProduct).toHaveBeenCalledWith(
        'mp-1',
        expect.objectContaining({ merchant_category_id: 'merchant-cat-3' }),
      ),
    );
  });

  it('keeps the catalogue usable when merchant categories fail to load', async () => {
    vi.mocked(listMerchantCategories).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.getByText('Impossible de charger les catégories marchand.')).toBeInTheDocument();
    fireEvent.click(screen.getAllByRole('button', { name: 'Modifier' })[0]);
    expect(screen.getByText('Aucune catégorie marchand active disponible.')).toBeInTheDocument();
  });

  it('searches product references from the add product drawer', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));
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

  it('opens guided assistant for catalogue enrichment', async () => {
    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    expect(screen.getByRole('dialog', { name: 'Assistant catalogue' })).toBeInTheDocument();
    expect(screen.getByText('1. Chercher')).toBeInTheDocument();
    expect(screen.getByText('2. Configurer')).toBeInTheDocument();
    expect(screen.getByText('3. Publier')).toBeInTheDocument();
  });

  it('previews and commits catalogue import from a photo in the guided assistant', async () => {
    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const photo = new File(['fake'], 'ticket.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Photo ticket, rayon ou liste papier'), {
      target: { files: [photo] },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Analyser la photo' }));

    await waitFor(() =>
      expect(previewMerchantCatalogPhotoImport).toHaveBeenCalledWith('store-1', photo, 'receipt'),
    );

    expect(await screen.findByText('2 produits détectés · 1 match référentiel · 1 à créer localement')).toBeInTheDocument();
    expect(screen.getByText('Ligne 1 · Référentiel · Lait demi-écrémé · 1.650 TND')).toBeInTheDocument();
    expect(screen.getByText('Ligne 2 · À créer localement · Harissa maison · 4.500 TND')).toBeInTheDocument();

    fireEvent.change(screen.getAllByLabelText('Prix TND')[0], { target: { value: '1,700' } });
    fireEvent.click(screen.getByRole('button', { name: 'Valider l’import photo' }));

    await waitFor(() =>
      expect(commitMerchantCatalogPhotoImport).toHaveBeenCalledWith('store-1', {
        items: [
          expect.objectContaining({
            line: 1,
            selected: true,
            product_reference_id: 'ref-photo-1',
            price_tnd: '1.700',
            is_available: true,
            is_visible: true,
          }),
          expect.objectContaining({
            line: 2,
            selected: true,
            product_reference_id: null,
            price_tnd: '4.500',
            is_available: true,
            is_visible: true,
          }),
        ],
      }),
    );
    expect(await screen.findByText('2 créé, 0 mis à jour, 0 ignoré')).toBeInTheDocument();
    await waitFor(() => expect(listMerchantCatalog).toHaveBeenCalledTimes(2));
  });

  it('shows photo import commit errors line by line', async () => {
    vi.mocked(commitMerchantCatalogPhotoImport).mockResolvedValueOnce({
      id: 'store-1',
      created: 0,
      updated: 0,
      ignored: 0,
      items: [],
      errors: [
        {
          line: 2,
          code: 'LOCAL_PRODUCT_FIELDS_REQUIRED',
          field: null,
          message: "La marque, le volume et l'unité sont obligatoires.",
        },
      ],
    });

    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const photo = new File(['fake'], 'ticket.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Photo ticket, rayon ou liste papier'), {
      target: { files: [photo] },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Analyser la photo' }));
    await screen.findByText('2 produits détectés · 1 match référentiel · 1 à créer localement');

    fireEvent.click(screen.getByRole('button', { name: 'Valider l’import photo' }));

    expect(await screen.findByText('0 créé, 0 mis à jour, 0 ignoré')).toBeInTheDocument();
    expect(
      screen.getByText("Ligne 2 · LOCAL_PRODUCT_FIELDS_REQUIRED · La marque, le volume et l'unité sont obligatoires."),
    ).toBeInTheDocument();
  });

  it('disables photo import commit while a selected row has no price', async () => {
    vi.mocked(previewMerchantCatalogPhotoImport).mockResolvedValueOnce({
      id: 'store-1',
      source_type: 'receipt',
      detected_count: 1,
      matched_reference_count: 1,
      local_candidate_count: 0,
      items: [
        {
          line: 1,
          status: 'matched_reference',
          product_reference_id: 'ref-photo-1',
          name_fr: 'Lait demi-écrémé',
          brand: 'Vitalait',
          volume: '1.000',
          unit: 'litre',
          barcode: '6191234567890',
          suggested_price_tnd: null,
          confidence: '0.940',
          already_in_catalog: false,
        },
      ],
    });

    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const photo = new File(['fake'], 'ticket.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Photo ticket, rayon ou liste papier'), {
      target: { files: [photo] },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Analyser la photo' }));

    const commitButton = await screen.findByRole('button', { name: 'Valider l’import photo' });
    expect(commitButton).toBeDisabled();

    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '1,650' } });

    expect(commitButton).toBeEnabled();
  });

  it('lets merchants correct required local photo import fields before commit', async () => {
    vi.mocked(previewMerchantCatalogPhotoImport).mockResolvedValueOnce({
      id: 'store-1',
      source_type: 'receipt',
      detected_count: 1,
      matched_reference_count: 0,
      local_candidate_count: 1,
      items: [
        {
          line: 1,
          status: 'local_candidate',
          product_reference_id: null,
          name_fr: 'Harissa',
          brand: null,
          volume: null,
          unit: null,
          barcode: null,
          suggested_price_tnd: '4.500',
          confidence: '0.820',
          already_in_catalog: false,
        },
      ],
    });

    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const photo = new File(['fake'], 'ticket.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Photo ticket, rayon ou liste papier'), {
      target: { files: [photo] },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Analyser la photo' }));

    fireEvent.change(await screen.findByLabelText('Nom produit'), { target: { value: 'Harissa maison' } });
    fireEvent.change(screen.getByLabelText('Marque'), { target: { value: 'Jouda' } });
    fireEvent.change(screen.getByLabelText('Volume'), { target: { value: '350' } });
    fireEvent.change(screen.getByLabelText('Unité'), { target: { value: 'gramme' } });
    fireEvent.click(screen.getByRole('button', { name: 'Valider l’import photo' }));

    await waitFor(() =>
      expect(commitMerchantCatalogPhotoImport).toHaveBeenCalledWith('store-1', {
        items: [
          expect.objectContaining({
            line: 1,
            product_reference_id: null,
            name_fr: 'Harissa maison',
            brand: 'Jouda',
            volume: '350',
            unit: 'gramme',
            price_tnd: '4.500',
          }),
        ],
      }),
    );
  });

  it('commits only selected photo import rows', async () => {
    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const photo = new File(['fake'], 'ticket.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText('Photo ticket, rayon ou liste papier'), {
      target: { files: [photo] },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Analyser la photo' }));

    await screen.findByText('2 produits détectés · 1 match référentiel · 1 à créer localement');
    fireEvent.click(screen.getByLabelText('Ligne 2 · Harissa maison'));
    fireEvent.click(screen.getByRole('button', { name: 'Valider l’import photo' }));

    await waitFor(() =>
      expect(commitMerchantCatalogPhotoImport).toHaveBeenCalledWith('store-1', {
        items: [
          expect.objectContaining({
            line: 1,
            selected: true,
            product_reference_id: 'ref-photo-1',
          }),
        ],
      }),
    );
  });

  it('imports a CSV file and shows a line-by-line report', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');

    const csvFile = new File(
      ['name_fr,brand,volume,unit,price_tnd,is_available,is_visible\nLait,Vitalait,1,litre,1.650,true,true\n'],
      'catalogue.csv',
      { type: 'text/csv' },
    );
    fireEvent.change(screen.getByLabelText('Fichier CSV catalogue'), {
      target: { files: [csvFile] },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Importer CSV' }));

    await waitFor(() =>
      expect(importMerchantCatalogCsv).toHaveBeenCalledWith(
        'store-1',
        'name_fr,brand,volume,unit,price_tnd,is_available,is_visible\nLait,Vitalait,1,litre,1.650,true,true\n',
      ),
    );
    expect(await screen.findByText('1 créé, 1 mis à jour, 0 ignoré')).toBeInTheDocument();
    expect(screen.getByText('Ligne 2 · created · Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.getByText('Ligne 4 · price_tnd · Le prix est obligatoire.')).toBeInTheDocument();
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('searches a barcode manually and adds the matching product reference', async () => {
    vi.mocked(searchMerchantProductReferences).mockResolvedValue({
      items: [
        {
          id: 'ref-barcode-1',
          name_fr: 'Eau minérale Safia',
          name_ar: null,
          brand_id: 'brand-1',
          brand: 'Safia',
          category_id: 'cat-1',
          category: 'Eaux',
          category_ar: null,
          category_slug: 'eaux',
          volume: '1.5',
          unit: 'litre',
          barcode: '6191234567890',
          already_in_catalog: false,
        },
      ],
      total: 1,
      page: 1,
      limit: 10,
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.change(screen.getByLabelText('Code-barres'), {
      target: { value: '6191234567890' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher le code-barres' }));

    await waitFor(() =>
      expect(searchMerchantProductReferences).toHaveBeenCalledWith('store-1', {
        barcode: '6191234567890',
        page: 1,
        limit: 10,
      }),
    );

    fireEvent.click(await screen.findByRole('button', { name: 'Choisir Eau minérale Safia' }));
    fireEvent.change(screen.getByLabelText('Prix TND pour le code-barres'), {
      target: { value: '1,200' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter le produit scanné' }));

    await waitFor(() =>
      expect(addMerchantCatalogProduct).toHaveBeenCalledWith('store-1', {
        product_reference_id: 'ref-barcode-1',
        price_tnd: '1.200',
        is_available: true,
        is_visible: true,
        merchant_note: null,
        merchant_category_id: null,
      }),
    );
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('attaches the camera stream to the mounted video before barcode detection', async () => {
    const stream = new MediaStream();
    Object.defineProperty(stream, 'getTracks', {
      configurable: true,
      value: vi.fn(() => [{ stop: vi.fn() } as unknown as MediaStreamTrack]),
    });
    const getUserMedia = vi.fn().mockResolvedValue(stream);
    const detect = vi.fn().mockResolvedValue([{ rawValue: '6191234567890' }]);
    const frameCallbacks: FrameRequestCallback[] = [];

    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia },
    });
    class FakeBarcodeDetector {
      detect = detect;
    }
    Object.defineProperty(window, 'BarcodeDetector', {
      configurable: true,
      value: FakeBarcodeDetector,
    });
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined);
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      frameCallbacks.push(callback);
      return frameCallbacks.length;
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Ouvrir caméra' }));

    await waitFor(() => expect(getUserMedia).toHaveBeenCalledTimes(1));
    const video = await screen.findByText('Caméra ouverte. Cadre le code-barres ou utilise la saisie manuelle.')
      .then(() => document.querySelector('video'));
    expect(video?.srcObject).toBe(stream);
    Object.defineProperty(video, 'readyState', {
      configurable: true,
      value: 2,
    });
    expect(frameCallbacks).toHaveLength(1);
    frameCallbacks[0](0);
    await waitFor(() => expect(detect).toHaveBeenCalledWith(video));
    await waitFor(() => expect(screen.getByLabelText('Code-barres')).toHaveValue('6191234567890'));
  });

  it('waits for a camera video frame before detecting a barcode', async () => {
    const stream = new MediaStream();
    Object.defineProperty(stream, 'getTracks', {
      configurable: true,
      value: vi.fn(() => [{ stop: vi.fn() } as unknown as MediaStreamTrack]),
    });
    const getUserMedia = vi.fn().mockResolvedValue(stream);
    const detect = vi.fn().mockResolvedValue([{ rawValue: '6191234567890' }]);
    const frameCallbacks: FrameRequestCallback[] = [];

    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia },
    });
    class FakeBarcodeDetector {
      detect = detect;
    }
    Object.defineProperty(window, 'BarcodeDetector', {
      configurable: true,
      value: FakeBarcodeDetector,
    });
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined);
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      frameCallbacks.push(callback);
      return frameCallbacks.length;
    });

    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Ouvrir caméra' }));

    await waitFor(() => expect(getUserMedia).toHaveBeenCalledTimes(1));
    const video = await screen.findByText('Caméra ouverte. Cadre le code-barres ou utilise la saisie manuelle.')
      .then(() => document.querySelector('video'));

    expect(video).not.toBeNull();
    Object.defineProperty(video, 'readyState', {
      configurable: true,
      value: 0,
    });

    expect(frameCallbacks).toHaveLength(1);
    frameCallbacks[0](0);

    expect(detect).not.toHaveBeenCalled();
    expect(frameCallbacks).toHaveLength(2);

    Object.defineProperty(video, 'readyState', {
      configurable: true,
      value: 2,
    });
    frameCallbacks[1](16);

    await waitFor(() => expect(detect).toHaveBeenCalledWith(video));
    await waitFor(() => expect(screen.getByLabelText('Code-barres')).toHaveValue('6191234567890'));
  });

  it('keeps tab focus inside the guided assistant', async () => {
    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const dialog = screen.getByRole('dialog', { name: 'Assistant catalogue' });
    const closeButton = within(dialog).getByRole('button', { name: 'Fermer' });
    const sourceSelect = within(dialog).getByLabelText('Source photo');

    expect(closeButton).toHaveFocus();

    sourceSelect.focus();
    fireEvent.keyDown(dialog, { key: 'Tab' });

    expect(closeButton).toHaveFocus();

    fireEvent.keyDown(dialog, { key: 'Tab', shiftKey: true });

    expect(sourceSelect).toHaveFocus();
  });

  it('opens the existing product reference drawer from the guided assistant', async () => {
    render(React.createElement(MerchantCatalogPage));

    fireEvent.click(await screen.findByRole('button', { name: "M'aider à ajouter des produits" }));

    const dialog = screen.getByRole('dialog', { name: 'Assistant catalogue' });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Ajouter un produit connu' }));

    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: 'Assistant catalogue' })).not.toBeInTheDocument(),
    );
    expect(screen.getByRole('dialog', { name: 'Ajouter un produit' })).toBeInTheDocument();
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
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));
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
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));
    fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
      target: { value: 'couscous' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Couscous fin' }));

    expect(screen.getByText('Catégorie référentiel : Epicerie')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '2.400' } });
    fireEvent.change(screen.getByLabelText('Catégorie marchand'), {
      target: { value: 'merchant-cat-1' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter à mon catalogue' }));

    await waitFor(() =>
      expect(addMerchantCatalogProduct).toHaveBeenCalledWith('store-1', {
        product_reference_id: 'ref-1',
        price_tnd: '2.400',
        is_available: true,
        is_visible: true,
        merchant_note: null,
        merchant_category_id: 'merchant-cat-1',
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('creates a local product in the merchant catalogue', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Créer un produit de ma supérette' }));

    expect(screen.getByRole('dialog', { name: 'Créer un produit local' })).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Nom en français'), {
      target: { value: 'Harissa maison' },
    });
    fireEvent.change(screen.getByLabelText('Catégorie par défaut'), {
      target: { value: 'Epicerie' },
    });
    fireEvent.change(screen.getByLabelText('Volume'), { target: { value: '350' } });
    fireEvent.change(screen.getByLabelText('Unité'), { target: { value: 'gramme' } });
    fireEvent.change(screen.getByLabelText('Prix TND', { exact: false }), { target: { value: '4,5' } });
    fireEvent.change(screen.getByLabelText('Catégorie marchand'), {
      target: { value: 'merchant-cat-1' },
    });
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
        merchant_category_id: 'merchant-cat-1',
        pack_quantity: 1,
      }),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
  });

  it('rejects blank local product names without creating it', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Créer un produit de ma supérette' }));
    fireEvent.change(screen.getByLabelText('Nom en français'), { target: { value: '   ' } });
    fireEvent.change(screen.getByLabelText('Prix TND', { exact: false }), { target: { value: '4.500' } });
    fireEvent.click(screen.getByRole('button', { name: 'Créer dans mon catalogue' }));

    expect(screen.getByRole('alert')).toHaveTextContent('Le nom en français est obligatoire.');
    expect(createMerchantLocalProduct).not.toHaveBeenCalled();
  });

  it('rejects invalid local product prices without creating it', async () => {
    render(React.createElement(MerchantCatalogPage));

    await screen.findByText('Lait demi-écrémé');
    fireEvent.click(screen.getByRole('button', { name: 'Créer un produit de ma supérette' }));
    fireEvent.change(screen.getByLabelText('Nom en français'), {
      target: { value: 'Harissa maison' },
    });
    fireEvent.change(screen.getByLabelText('Prix TND', { exact: false }), { target: { value: '0' } });
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
    fireEvent.click(screen.getByRole('button', { name: 'Créer un produit de ma supérette' }));
    fireEvent.change(screen.getByLabelText('Nom en français'), {
      target: { value: 'Harissa maison' },
    });
    fireEvent.change(screen.getByLabelText('Prix TND', { exact: false }), { target: { value: '4.500' } });
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
      pack_quantity: 1,
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
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));
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
        merchant_category_id: null,
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
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));

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
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));
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
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter un produit connu' }));
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
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult(manyProducts));

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

    await waitFor(() =>
      expect(screen.queryByRole('checkbox', { name: 'Sélectionner Lait demi-écrémé' })).not.toBeInTheDocument(),
    );
    expect(screen.getByText('1 produit mis à jour.')).toBeInTheDocument();
  });

  it('filters merchant catalogue products via server-side API call after submit', async () => {
    vi.mocked(listMerchantCatalog)
      .mockResolvedValueOnce(catalogResult(products))
      .mockResolvedValueOnce(catalogResult([products[0]]));

    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.getByText('Couscous fin')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Rechercher dans le catalogue'), {
      target: { value: 'lait' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByText('Couscous fin')).not.toBeInTheDocument());
    expect(listMerchantCatalog).toHaveBeenCalledTimes(2);
    expect(listMerchantCatalog).toHaveBeenLastCalledWith(
      'store-1',
      expect.objectContaining({ q: 'lait', page: 1 }),
    );
  });

  it('renders a dedicated empty state when filters produce no results from the API', async () => {
    vi.mocked(listMerchantCatalog)
      .mockResolvedValueOnce(catalogResult(products))
      .mockResolvedValueOnce({ items: [], total: 0, page: 1, limit: 50, pages: 1 });

    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Lait demi-écrémé')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Rechercher dans le catalogue'), {
      target: { value: 'introuvable' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    expect(await screen.findByText('Aucun produit ne correspond aux filtres.')).toBeInTheDocument();
    expect(screen.queryByText('Aucun produit dans ce catalogue.')).not.toBeInTheDocument();
  });

  it('can retry after an error and render an empty catalogue', async () => {
    vi.mocked(listMerchantCatalog)
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce({ items: [], total: 0, page: 1, limit: 50, pages: 1 });

    render(React.createElement(MerchantCatalogPage));

    expect(await screen.findByText('Impossible de charger le catalogue.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    expect(await screen.findByText('Aucun produit dans ce catalogue.')).toBeInTheDocument();
    await waitFor(() => expect(listMerchantCatalog).toHaveBeenCalledTimes(2));
  });

  it('disables retry while the catalogue is loading', async () => {
    const pendingCatalog = deferred<MerchantCatalogListResult>();
    vi.mocked(listMerchantCatalog).mockReturnValue(pendingCatalog.promise);

    render(React.createElement(MerchantCatalogPage));

    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeDisabled();

    pendingCatalog.resolve(catalogResult(products));

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Réessayer' })).not.toBeDisabled(),
    );
  });
});
