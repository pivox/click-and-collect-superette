import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getMerchantMe,
  loginMerchant,
} from '@/lib/services/merchant-auth.service';
import { getMerchantDashboardToday } from '@/lib/services/merchant-dashboard.service';
import {
  listMerchantOrderHistory,
  listMerchantOrders,
} from '@/lib/services/merchant-orders.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

function makeToken(payload: Record<string, unknown>): string {
  return `header.${btoa(JSON.stringify(payload))}.sig`;
}

describe('merchant services', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('logs in a merchant through the existing JWT endpoint', async () => {
    const token = makeToken({
      email: 'marchand@kadhia.tn',
      roles: ['ROLE_MERCHANT'],
    });
    vi.mocked(apiClient.post).mockResolvedValue({ data: { token } });

    const user = await loginMerchant({
      email: 'marchand@kadhia.tn',
      password: 'secret',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/auth/login', {
      email: 'marchand@kadhia.tn',
      password: 'secret',
    });
    expect(user).toEqual({
      token,
      email: 'marchand@kadhia.tn',
    });
  });

  it('rejects JWT tokens without ROLE_MERCHANT', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { token: makeToken({ email: 'client@kadhia.tn', roles: ['ROLE_CUSTOMER'] }) },
    });

    await expect(
      loginMerchant({ email: 'client@kadhia.tn', password: 'secret' }),
    ).rejects.toThrow('Accès réservé aux marchands');
  });

  it('loads the current merchant context', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        user_id: 'user-1',
        email: 'marchand@kadhia.tn',
        roles: ['ROLE_MERCHANT'],
        store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
        onboarding_completed: false,
      },
    });

    const context = await getMerchantMe();

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/me');
    expect(context.store.name).toBe('Supérette Ezzahra');
  });

  it('loads dashboard and order lists for the active supérette', async () => {
    vi.mocked(apiClient.get)
      .mockResolvedValueOnce({ data: { store_id: 'store-1', pickup_slots_today: [] } })
      .mockResolvedValueOnce({ data: { items: [], total: 0, page: 1, limit: 20 } })
      .mockResolvedValueOnce({ data: { items: [], total: 0, page: 1, limit: 20 } });

    await getMerchantDashboardToday('store-1');
    await listMerchantOrders('store-1');
    await listMerchantOrderHistory('store-1');

    expect(apiClient.get).toHaveBeenNthCalledWith(
      1,
      '/api/merchant/stores/store-1/dashboard/today',
    );
    expect(apiClient.get).toHaveBeenNthCalledWith(
      2,
      '/api/merchant/stores/store-1/orders',
      { params: { page: 1, limit: 20 } },
    );
    expect(apiClient.get).toHaveBeenNthCalledWith(
      3,
      '/api/merchant/stores/store-1/orders/history',
      { params: { page: 1, limit: 20 } },
    );
  });
});
