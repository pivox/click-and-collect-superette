import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantClosures,
  createMerchantClosure,
  patchMerchantClosure,
  deleteMerchantClosure,
} from '@/lib/services/merchant-closures.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const STORE_ID = '11111111-1111-4111-8111-111111111111';
const CLOSURE_ID = '44444444-4444-4444-8444-444444444444';

const closure = {
  id: CLOSURE_ID,
  starts_at: '2026-06-01T00:00:00+01:00',
  ends_at: '2026-06-01T23:59:00+01:00',
  reason: 'Aïd el-Fitr',
  is_active: true,
};

describe('merchant closures service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists closures for a store', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { total: 1, items: [closure] },
    });

    const result = await listMerchantClosures(STORE_ID);

    expect(apiClient.get).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures`,
    );
    expect(result.total).toBe(1);
    expect(result.items[0].reason).toBe('Aïd el-Fitr');
  });

  it('creates a closure', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: closure });

    const result = await createMerchantClosure(STORE_ID, {
      starts_at: '2026-06-01T00:00:00+01:00',
      ends_at: '2026-06-01T23:59:00+01:00',
      reason: 'Aïd el-Fitr',
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures`,
      {
        starts_at: '2026-06-01T00:00:00+01:00',
        ends_at: '2026-06-01T23:59:00+01:00',
        reason: 'Aïd el-Fitr',
      },
    );
    expect(result.id).toBe(CLOSURE_ID);
  });

  it('patches a closure reason', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { ...closure, reason: 'Congé annuel' },
    });

    const result = await patchMerchantClosure(STORE_ID, CLOSURE_ID, {
      reason: 'Congé annuel',
    });

    expect(apiClient.patch).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures/${CLOSURE_ID}`,
      { reason: 'Congé annuel' },
    );
    expect(result.reason).toBe('Congé annuel');
  });

  it('deletes a closure', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: undefined });

    await deleteMerchantClosure(STORE_ID, CLOSURE_ID);

    expect(apiClient.delete).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures/${CLOSURE_ID}`,
    );
  });
});
