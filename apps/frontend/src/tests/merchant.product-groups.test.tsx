import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantCatalogPage from '@/app/merchant/catalogue/page';
import {
  bulkUpdateMerchantProductAvailability,
  getMerchantProductGroup,
  importMerchantProductGroup,
  listMerchantCategories,
  listMerchantCatalog,
  listMerchantProductGroups,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogListResult,
  MerchantCatalogProduct,
  MerchantCategory,
  MerchantProductGroup,
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
    bulkUpdateMerchantProductAvailability: vi.fn(),
    getMerchantProductGroup: vi.fn(),
    importMerchantProductGroup: vi.fn(),
    listMerchantCategories: vi.fn(),
    listMerchantCatalog: vi.fn(),
    listMerchantProductGroups: vi.fn(),
  };
});

const products: MerchantCatalogProduct[] = [
  {
    id: 'mp-1',
    product_reference_id: 'ref-present',
    name_fr: 'Eau 1.5L',
    brand: 'Sabrine',
    category: 'Boissons',
    merchant_category_name: null,
    volume: '1.500',
    unit: 'litre',
    price_tnd: '1.000',
    is_available: true,
    is_visible: true,
    merchant_note: null,
  },
];

const categories: MerchantCategory[] = [];

const groupDetail: MerchantProductGroup = {
  id: 'group-1',
  name_fr: 'Premières nécessités',
  name_ar: null,
  slug: 'premieres-necessites',
  description_fr: 'Produits de base',
  description_ar: null,
  market_country: 'TN',
  icon: 'shopping-basket',
  sort_order: 10,
  items_count: 3,
  items: [
    {
      id: 'item-new',
      sort_order: 10,
      importance: 'required',
      status: 'new',
      product_reference: {
        id: 'ref-new',
        name_fr: 'Lait UHT',
        name_ar: null,
        brand_name: 'Vitalait',
        category_name_fr: 'Lait',
        unit: 'litre',
        volume: '1.000',
        status: 'approved',
      },
    },
    {
      id: 'item-present',
      sort_order: 20,
      importance: 'recommended',
      status: 'already_present',
      product_reference: {
        id: 'ref-present',
        name_fr: 'Eau 1.5L',
        name_ar: null,
        brand_name: 'Sabrine',
        category_name_fr: 'Boissons',
        unit: 'litre',
        volume: '1.500',
        status: 'approved',
      },
    },
    {
      id: 'item-blocked',
      sort_order: 30,
      importance: 'optional',
      status: 'non_importable',
      product_reference: {
        id: 'ref-blocked',
        name_fr: 'Produit non approuvé',
        name_ar: null,
        brand_name: 'Test',
        category_name_fr: 'Test',
        unit: 'piece',
        volume: null,
        status: 'draft',
      },
    },
  ],
};

function catalogResult(items: MerchantCatalogProduct[]): MerchantCatalogListResult {
  return { items, total: items.length, page: 1, limit: 50, pages: 1 };
}

describe('MerchantCatalogPage product groups', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchantCatalog).mockResolvedValue(catalogResult(products));
    vi.mocked(listMerchantCategories).mockResolvedValue(categories);
    vi.mocked(bulkUpdateMerchantProductAvailability).mockResolvedValue({
      updated_count: 0,
      is_available: true,
      merchant_note: null,
      merchant_product_ids: [],
    });
    vi.mocked(listMerchantProductGroups).mockResolvedValue({
      id: 'store-1',
      items: [
        {
          ...groupDetail,
          items: undefined,
        },
      ],
    });
    vi.mocked(getMerchantProductGroup).mockResolvedValue(groupDetail);
    vi.mocked(importMerchantProductGroup).mockResolvedValue({
      created: 1,
      alreadyInCatalog: 0,
      skipped: 0,
      requiresPriceCompletion: 1,
      errors: [],
    });
  });

  it('opens product groups, selects only new lines by default, imports them, and refreshes the catalog', async () => {
    render(<MerchantCatalogPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Ajouter par groupement' }));

    await waitFor(() => expect(listMerchantProductGroups).toHaveBeenCalledWith('store-1'));
    fireEvent.click(await screen.findByRole('button', { name: /Premières nécessités/ }));

    await waitFor(() => expect(getMerchantProductGroup).toHaveBeenCalledWith('store-1', 'group-1'));
    const dialog = screen.getByRole('dialog', { name: 'Ajouter par groupement' });

    const newCheckbox = within(dialog).getByRole('checkbox', { name: /Lait UHT/ });
    const presentCheckbox = within(dialog).getByRole('checkbox', { name: /Eau 1.5L/ });
    const blockedCheckbox = within(dialog).getByRole('checkbox', { name: /Produit non approuvé/ });
    expect(newCheckbox).toBeChecked();
    expect(presentCheckbox).toBeDisabled();
    expect(blockedCheckbox).toBeDisabled();

    vi.mocked(importMerchantProductGroup).mockResolvedValueOnce({
      created: 1,
      alreadyInCatalog: 1,
      skipped: 1,
      requiresPriceCompletion: 1,
      errors: [
        {
          productReferenceId: 'ref-skipped',
          code: 'PRODUCT_REFERENCE_NOT_IN_GROUP',
          message: 'Selected product reference does not belong to the product group.',
        },
      ],
    });

    fireEvent.click(within(dialog).getByRole('button', { name: 'Importer la sélection' }));

    await waitFor(() =>
      expect(importMerchantProductGroup).toHaveBeenCalledWith('store-1', {
        groupId: 'group-1',
        selectedProductReferenceIds: ['ref-new'],
        skipExisting: true,
        defaultVisibility: false,
        defaultAvailability: true,
      }),
    );
    expect(await within(dialog).findByText('Import terminé')).toBeInTheDocument();
    expect(within(dialog).getByText('1 créé')).toBeInTheDocument();
    expect(within(dialog).getByText('1 déjà au catalogue')).toBeInTheDocument();
    expect(within(dialog).getByText('1 ignoré')).toBeInTheDocument();
    expect(within(dialog).getByText('1 prix à compléter')).toBeInTheDocument();
    expect(within(dialog).getByText(/Selected product reference does not belong/)).toBeInTheDocument();
    await waitFor(() => expect(listMerchantCatalog).toHaveBeenCalledTimes(2));
    expect(within(dialog).queryByText(/ref-present/)).not.toBeInTheDocument();
    expect(within(dialog).queryByText(/ref-blocked/)).not.toBeInTheDocument();
  });
});
