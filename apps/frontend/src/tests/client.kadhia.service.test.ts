import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

import { listMyKadhias } from '@/lib/services/kadhia.service';

const RAW_LIST_ITEM = {
  id: 'k-1',
  store_id: 'store-1',
  store_name: 'Épicerie Test',
  status: 'draft',
  lines_count: 3,
  total_tnd: '8.500',
  updated_at: '2026-06-01T10:00:00Z',
  notes: 'courses maison',
};

describe('listMyKadhias', () => {
  beforeEach(() => vi.clearAllMocks());

  it('appelle /api/me/kadhias sans paramètres quand appelé sans arguments', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, pages: 1 },
    });

    await listMyKadhias();

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/kadhias');
  });

  it('envoie status dans la query string', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, pages: 1 },
    });

    await listMyKadhias('draft');

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/kadhias?status=draft');
  });

  it("n'envoie pas page=1 dans la query string (page 1 est le défaut backend)", async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, pages: 1 },
    });

    await listMyKadhias('draft', 1);

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/kadhias?status=draft');
  });

  it('envoie page=N quand page > 1 avec status', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 5, page: 2, pages: 3 },
    });

    await listMyKadhias('submitted', 2);

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/kadhias?status=submitted&page=2');
  });

  it('envoie page=N sans status quand status est absent', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 2, pages: 1 },
    });

    await listMyKadhias(undefined, 2);

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/kadhias?page=2');
  });

  it('mappe correctement les champs de la réponse', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [RAW_LIST_ITEM], total: 1, page: 1, pages: 1 },
    });

    const result = await listMyKadhias();

    expect(result.total).toBe(1);
    expect(result.page).toBe(1);
    expect(result.pages).toBe(1);
    expect(result.items).toHaveLength(1);
    expect(result.items[0]).toMatchObject({
      id: 'k-1',
      storeId: 'store-1',
      storeName: 'Épicerie Test',
      status: 'draft',
      linesCount: 3,
      totalTnd: '8.500',
      updatedAt: '2026-06-01T10:00:00Z',
      notes: 'courses maison',
    });
  });

  it('retourne pages et total corrects sur plusieurs pages', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 42, page: 3, pages: 5 },
    });

    const result = await listMyKadhias('draft', 3);

    expect(result.total).toBe(42);
    expect(result.page).toBe(3);
    expect(result.pages).toBe(5);
  });

  it('mappe notes à undefined quand absent de la réponse', async () => {
    const itemWithoutNotes = { ...RAW_LIST_ITEM, notes: undefined };
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [itemWithoutNotes], total: 1, page: 1, pages: 1 },
    });

    const result = await listMyKadhias();

    expect(result.items[0].notes).toBeUndefined();
  });
});
