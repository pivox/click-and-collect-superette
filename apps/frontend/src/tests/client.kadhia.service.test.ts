import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: { delete: vi.fn(), get: vi.fn(), post: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

import {
  countStoreDraftKadhias,
  createKadhiaShareLink,
  discardKadhia,
  getCurrentKadhia,
  joinKadhiaShareLink,
  listMyKadhias,
  submitKadhia,
} from '@/lib/services/kadhia.service';

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

describe('countStoreDraftKadhias', () => {
  beforeEach(() => vi.clearAllMocks());

  it('demande le décompte filtré status=draft et retourne le total (robuste à la pagination)', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        // total reflète l'ensemble des drafts, pas seulement la page courante
        items: [{ ...RAW_LIST_ITEM, id: 'k-1', status: 'draft' }],
        total: 23,
      },
    });

    const count = await countStoreDraftKadhias('store-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/stores/store-1/kadhias?status=draft', { skipAuthRedirect: true });
    expect(count).toBe(23);
  });

  it('retourne 0 pour un visiteur anonyme (401)', async () => {
    vi.mocked(apiClient.get).mockRejectedValue({ response: { status: 401 } });

    expect(await countStoreDraftKadhias('store-1')).toBe(0);
  });

  it('propage les autres erreurs réseau', async () => {
    vi.mocked(apiClient.get).mockRejectedValue({ response: { status: 500 } });

    await expect(countStoreDraftKadhias('store-1')).rejects.toEqual({ response: { status: 500 } });
  });
});

describe('partage de Kadhia', () => {
  beforeEach(() => vi.clearAllMocks());

  it('createKadhiaShareLink appelle le bon endpoint et mappe la réponse', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: {
        share_url: 'http://localhost:3000/kadhia/share/tok-1',
        expires_at: '2026-06-20T12:00:00+01:00',
      },
    });

    const result = await createKadhiaShareLink('k-1');

    expect(apiClient.post).toHaveBeenCalledWith('/api/me/kadhias/k-1/share-links', {});
    expect(result).toEqual({
      shareUrl: 'http://localhost:3000/kadhia/share/tok-1',
      expiresAt: '2026-06-20T12:00:00+01:00',
    });
  });

  it('joinKadhiaShareLink appelle le bon endpoint et active la Kadhia partagée', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: {
        kadhia_id: 'k-1',
        store_id: 'store-1',
        joined: true,
      },
    });

    const result = await joinKadhiaShareLink('tok-1');

    expect(apiClient.post).toHaveBeenCalledWith('/api/me/kadhia-share-links/tok-1/join', {});
    expect(window.localStorage.getItem('kadhia:active:store-1')).toBe('k-1');
    expect(window.localStorage.getItem('kadhia:context')).toBe(JSON.stringify({ shopId: 'store-1', kadhiaId: 'k-1' }));
    expect(result).toEqual({
      kadhiaId: 'k-1',
      storeId: 'store-1',
      joined: true,
    });
  });
});

describe('submitKadhia', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  it('utilise le contexte de navigation quand la clé active de la supérette est absente', async () => {
    window.localStorage.setItem('kadhia:context', JSON.stringify({ shopId: 'store-1', kadhiaId: 'k-context' }));
    vi.mocked(apiClient.post).mockResolvedValue({
      data: {
        id: 'order-1',
        order_number_display: '#0007',
      },
    });

    const result = await submitKadhia({
      shopId: 'store-1',
      pickupSlotId: 'slot-1',
      customerNote: 'Préparer les fruits séparément',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/me/kadhias/k-context/submit', {
      pickup_slot_id: 'slot-1',
      notes: 'Préparer les fruits séparément',
    });
    expect(window.localStorage.getItem('kadhia:active:store-1')).toBeNull();
    expect(window.localStorage.getItem('kadhia:context')).toBeNull();
    expect(result).toEqual({ orderId: 'order-1', orderCode: '#0007' });
  });
});

describe('discardKadhia', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  it('supprime la Kadhia depuis le contexte quand la clé active est absente', async () => {
    window.localStorage.setItem('kadhia:context', JSON.stringify({ shopId: 'store-1', kadhiaId: 'k-context' }));
    vi.mocked(apiClient.delete).mockResolvedValue({});

    await discardKadhia('store-1');

    expect(apiClient.delete).toHaveBeenCalledWith('/api/me/kadhias/k-context');
    expect(window.localStorage.getItem('kadhia:active:store-1')).toBeNull();
    expect(window.localStorage.getItem('kadhia:context')).toBeNull();
  });

  it('nettoie le cache local même si la suppression backend est refusée', async () => {
    window.localStorage.setItem('kadhia:active:store-1', 'k-shared');
    window.localStorage.setItem('kadhia:context', JSON.stringify({ shopId: 'store-1', kadhiaId: 'k-shared' }));
    vi.mocked(apiClient.delete).mockRejectedValue(new Error('Forbidden'));

    await expect(discardKadhia('store-1')).rejects.toThrow('Forbidden');

    expect(apiClient.delete).toHaveBeenCalledWith('/api/me/kadhias/k-shared');
    expect(window.localStorage.getItem('kadhia:active:store-1')).toBeNull();
    expect(window.localStorage.getItem('kadhia:context')).toBeNull();
  });

  it("n'auto-réactive pas une Kadhia partagée expirée après redémarrage forcé", async () => {
    window.localStorage.setItem('kadhia:active:store-1', 'k-shared');
    window.localStorage.setItem('kadhia:context', JSON.stringify({ shopId: 'store-1', kadhiaId: 'k-shared' }));
    vi.mocked(apiClient.delete).mockRejectedValue({ response: { status: 404 } });

    await expect(discardKadhia('store-1', { suppressReactivation: true })).rejects.toEqual({
      response: { status: 404 },
    });

    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        items: [{ ...RAW_LIST_ITEM, id: 'k-shared', status: 'draft' }],
        total: 1,
      },
    });

    await expect(getCurrentKadhia('store-1')).resolves.toEqual({ type: 'none' });
    expect(apiClient.get).toHaveBeenCalledWith('/api/me/stores/store-1/kadhias', { skipAuthRedirect: true });
    expect(apiClient.get).not.toHaveBeenCalledWith('/api/me/kadhias/k-shared', { skipAuthRedirect: true });
  });
});
