import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import GroupementsPage from '@/app/admin/referentiel/groupements/page';
import {
  addProductGroupItem,
  archiveProductGroup,
  createProductGroup,
  getProductGroup,
  listProductGroups,
  publishProductGroup,
} from '@/lib/services/admin/product-groups.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import type { ProductGroup, ProductGroupItem } from '@/lib/types/admin/referentiel.types';

vi.mock('@/lib/services/admin/product-groups.service', () => ({
  listProductGroups: vi.fn(),
  getProductGroup: vi.fn(),
  createProductGroup: vi.fn(),
  updateProductGroup: vi.fn(),
  publishProductGroup: vi.fn(),
  archiveProductGroup: vi.fn(),
  addProductGroupItem: vi.fn(),
  updateProductGroupItem: vi.fn(),
  deleteProductGroupItem: vi.fn(),
}));

vi.mock('@/lib/services/admin/product-references.service', () => ({
  listProductReferences: vi.fn(),
}));

const ITEM: ProductGroupItem = {
  id: 'item-1',
  sort_order: 1,
  importance: 'required',
  created_at: '2026-06-11T10:00:00+00:00',
  updated_at: '2026-06-11T10:00:00+00:00',
  product_reference: {
    id: 'product-1',
    name_fr: 'Lait demi-écrémé',
    name_ar: null,
    brand_name: 'Vitalait',
    category_name_fr: 'Lait',
    unit: 'piece',
    volume: null,
    status: 'approved',
  },
};

const GROUP: ProductGroup = {
  id: 'group-1',
  name_fr: 'Premières nécessités',
  name_ar: null,
  slug: 'premieres-necessites',
  description_fr: 'Base quotidienne',
  description_ar: null,
  market_country: 'TN',
  status: 'draft',
  visibility: 'merchant',
  icon: 'shopping-basket',
  sort_order: 1,
  items_count: 1,
  created_at: '2026-06-11T10:00:00+00:00',
  updated_at: '2026-06-11T10:00:00+00:00',
  items: [ITEM],
};

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(listProductGroups).mockResolvedValue({
    id: 'admin-product-groups',
    items: [GROUP],
    page: 1,
    limit: 20,
    total: 1,
  });
  vi.mocked(getProductGroup).mockResolvedValue(GROUP);
  vi.mocked(createProductGroup).mockResolvedValue({ ...GROUP, name_fr: 'Petit déjeuner' });
  vi.mocked(publishProductGroup).mockResolvedValue({ ...GROUP, status: 'published' });
  vi.mocked(archiveProductGroup).mockResolvedValue({ ...GROUP, status: 'archived' });
  vi.mocked(addProductGroupItem).mockResolvedValue(ITEM);
  vi.mocked(listProductReferences).mockResolvedValue({
    id: 'admin-product-references',
    items: [
      {
        id: 'product-1',
        name_fr: 'Lait demi-écrémé',
        name_ar: null,
        variant_fr: null,
        variant_ar: null,
        brand_id: 'brand-1',
        brand_name: 'Vitalait',
        category_id: 'category-1',
        category_name_fr: 'Lait',
        category_name_ar: null,
        unit: 'piece',
        volume: null,
        barcode: null,
        aliases: [],
        country: 'TN',
        status: 'approved',
        created_at: '2026-06-11T10:00:00+00:00',
        updated_at: '2026-06-11T10:00:00+00:00',
        quality_score: 80,
        quality_level: 'good',
      },
    ],
    page: 1,
    limit: 10,
    total: 1,
  });
});

describe('GroupementsPage', () => {
  it('rend la liste, crée un groupement et publie/archive', async () => {
    render(<GroupementsPage />);

    expect(await screen.findByText('Premières nécessités')).toBeInTheDocument();
    expect(screen.getAllByText('Brouillon').length).toBeGreaterThan(0);

    fireEvent.click(screen.getByRole('button', { name: 'Nouveau groupement' }));
    const drawer = screen.getByRole('dialog', { name: 'Nouveau groupement' });
    fireEvent.change(within(drawer).getByLabelText('Nom FR'), {
      target: { value: 'Petit déjeuner' },
    });
    fireEvent.click(within(drawer).getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(createProductGroup).toHaveBeenCalledWith(expect.objectContaining({
      nameFr: 'Petit déjeuner',
      visibility: 'merchant',
    })));

    fireEvent.click(screen.getByRole('button', { name: 'Publier Premières nécessités' }));
    await waitFor(() => expect(publishProductGroup).toHaveBeenCalledWith('group-1'));

    fireEvent.click(screen.getByRole('button', { name: 'Archiver Premières nécessités' }));
    await waitFor(() => expect(archiveProductGroup).toHaveBeenCalledWith('group-1'));
  });

  it('ouvre le détail, ajoute une référence produit et affiche le refus doublon', async () => {
    vi.mocked(addProductGroupItem)
      .mockResolvedValueOnce(ITEM)
      .mockRejectedValueOnce({ response: { status: 422, data: { detail: 'ADMIN_PRODUCT_GROUP_ITEM_DUPLICATE' } } });

    render(<GroupementsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir le détail Premières nécessités' }));

    const detail = await screen.findByRole('region', { name: 'Détail Premières nécessités' });
    expect(within(detail).getByText('Lait demi-écrémé')).toBeInTheDocument();

    fireEvent.change(within(detail).getByPlaceholderText('Rechercher un produit référentiel…'), {
      target: { value: 'lait' },
    });

    await waitFor(() => expect(listProductReferences).toHaveBeenCalledWith({ q: 'lait', limit: 10 }));
    fireEvent.click(await within(detail).findByRole('button', { name: 'Ajouter Lait demi-écrémé' }));

    await waitFor(() => expect(addProductGroupItem).toHaveBeenCalledWith('group-1', expect.objectContaining({
      productReferenceId: 'product-1',
    })));

    fireEvent.click(await within(detail).findByRole('button', { name: 'Ajouter Lait demi-écrémé' }));

    expect(await within(detail).findByText('Ce produit est déjà dans le groupement.')).toBeInTheDocument();
  });
});
