import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  addProductGroupItem,
  archiveProductGroup,
  createProductGroup,
  deleteProductGroupItem,
  getProductGroup,
  listProductGroups,
  publishProductGroup,
  updateProductGroup,
  updateProductGroupItem,
} from '@/lib/services/admin/product-groups.service';
import type {
  ProductGroup,
  ProductGroupItem,
  ProductGroupListResponse,
} from '@/lib/types/admin/referentiel.types';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockGet = vi.mocked(apiClient.get);
const mockPost = vi.mocked(apiClient.post);
const mockPatch = vi.mocked(apiClient.patch);
const mockDelete = vi.mocked(apiClient.delete);

const GROUP: ProductGroup = {
  id: 'group-1',
  name_fr: 'Premières nécessités',
  name_ar: null,
  slug: 'premieres-necessites',
  description_fr: null,
  description_ar: null,
  market_country: 'TN',
  status: 'draft',
  visibility: 'merchant',
  icon: 'shopping-basket',
  sort_order: 1,
  items_count: 0,
  created_at: '2026-06-11T10:00:00+00:00',
  updated_at: '2026-06-11T10:00:00+00:00',
  items: [],
};

const ITEM: ProductGroupItem = {
  id: 'item-1',
  sort_order: 2,
  importance: 'recommended',
  created_at: '2026-06-11T10:00:00+00:00',
  updated_at: '2026-06-11T10:00:00+00:00',
  product_reference: {
    id: 'product-1',
    name_fr: 'Lait',
    name_ar: null,
    brand_name: 'Vitalait',
    category_name_fr: 'Lait',
    unit: 'piece',
    volume: null,
    status: 'approved',
  },
};

beforeEach(() => {
  vi.clearAllMocks();
});

describe('admin product groups service', () => {
  it('liste les groupements avec filtres et pagination', async () => {
    const response: ProductGroupListResponse = {
      id: 'admin-product-groups',
      items: [GROUP],
      page: 2,
      limit: 10,
      total: 1,
    };
    mockGet.mockResolvedValue({ data: response });

    const result = await listProductGroups({
      q: 'lait',
      status: 'published',
      marketCountry: 'TN',
      page: 2,
      limit: 10,
    });

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-groups', {
      params: {
        page: 2,
        limit: 10,
        q: 'lait',
        status: 'published',
        market_country: 'TN',
      },
    });
    expect(result).toEqual(response);
  });

  it('appelle les endpoints CRUD et actions avec les bons payloads', async () => {
    mockGet.mockResolvedValue({ data: GROUP });
    mockPost.mockResolvedValue({ data: GROUP });
    mockPatch.mockResolvedValue({ data: GROUP });

    await getProductGroup('group-1');
    await createProductGroup({ nameFr: 'Petit déjeuner', visibility: 'merchant' });
    await updateProductGroup('group-1', { nameFr: 'Nouveau nom', sortOrder: 4 });
    await publishProductGroup('group-1');
    await archiveProductGroup('group-1');

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-groups/group-1');
    expect(mockPost).toHaveBeenCalledWith('/api/admin/product-groups', {
      nameFr: 'Petit déjeuner',
      visibility: 'merchant',
    });
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/product-groups/group-1', {
      nameFr: 'Nouveau nom',
      sortOrder: 4,
    });
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/product-groups/group-1/publish', {});
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/product-groups/group-1/archive', {});
  });

  it('gère les items de groupement', async () => {
    mockPost.mockResolvedValue({ data: ITEM });
    mockPatch.mockResolvedValue({ data: ITEM });
    mockDelete.mockResolvedValue({ data: undefined });

    await addProductGroupItem('group-1', {
      productReferenceId: 'product-1',
      sortOrder: 2,
      importance: 'recommended',
    });
    await updateProductGroupItem('group-1', 'item-1', {
      sortOrder: 1,
      importance: 'required',
    });
    await deleteProductGroupItem('group-1', 'item-1');

    expect(mockPost).toHaveBeenCalledWith('/api/admin/product-groups/group-1/items', {
      productReferenceId: 'product-1',
      sortOrder: 2,
      importance: 'recommended',
    });
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/product-groups/group-1/items/item-1', {
      sortOrder: 1,
      importance: 'required',
    });
    expect(mockDelete).toHaveBeenCalledWith('/api/admin/product-groups/group-1/items/item-1');
  });
});
