import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { searchStores } from '@/lib/services/store-search.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
  },
}));

describe('searchStores', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls GET /api/stores/search with query param', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0 },
    });

    await searchStores('marj');

    expect(apiClient.get).toHaveBeenCalledWith('/api/stores/search', {
      params: { query: 'marj' },
    });
  });

  it('returns items and total from the API response', async () => {
    const mockItems = [
      {
        store_id: 'uuid-1',
        name: 'Marjé El Amel',
        slug: 'marje-el-amel',
        city: 'Tunis',
        country: 'TN',
        is_active: true,
      },
    ];
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: mockItems, total: 1 },
    });

    const result = await searchStores('marj');

    expect(result.items).toHaveLength(1);
    expect(result.items[0].store_id).toBe('uuid-1');
    expect(result.items[0].name).toBe('Marjé El Amel');
    expect(result.total).toBe(1);
  });
});
