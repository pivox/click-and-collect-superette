import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getMerchantMe,
  loginMerchant,
} from '@/lib/services/merchant-auth.service';
import { getMerchantDashboardToday } from '@/lib/services/merchant-dashboard.service';
import {
  acceptMerchantOrder,
  getMerchantOrder,
  listMerchantOrderHistory,
  listMerchantOrders,
  markMerchantOrderReady,
  partiallyAcceptMerchantOrder,
  rejectMerchantOrder,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
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

  it('loads order detail and calls merchant order mutations', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        id: 'order-1',
        store_id: 'store-1',
        status: 'submitted',
        total_tnd: '18.500',
        pickup_slot: {
          id: 'slot-1',
          starts_at: '2026-05-24T10:00:00+01:00',
          ends_at: '2026-05-24T11:00:00+01:00',
        },
        notes: 'Sans sachet.',
        customer_name: 'Fatma Ben Ali',
        customer_phone: '+21620111222',
        rejection_reason: null,
        lines: [
          {
            merchant_product_id: 'mp-1',
            product_name: 'Lait Vitalait 1L',
            quantity: 2,
            unit_price_tnd: '1.700',
            line_total_tnd: '3.400',
            prepared: false,
          },
        ],
        created_at: '2026-05-24T08:00:00+01:00',
        updated_at: '2026-05-24T08:00:00+01:00',
      },
    });
    vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'order-1', status: 'accepted' } });
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { id: 'order-1', status: 'preparing', lines: [] },
    });

    const detail = await getMerchantOrder('store-1', 'order-1');
    await acceptMerchantOrder('store-1', 'order-1');
    await rejectMerchantOrder('store-1', 'order-1', { reason: 'Produit indisponible' });
    await partiallyAcceptMerchantOrder('store-1', 'order-1', {
      rejected_merchant_product_ids: ['mp-1'],
      notes: 'Rupture.',
    });
    await startMerchantOrderPreparation('store-1', 'order-1');
    await setMerchantOrderLinePrepared('store-1', 'order-1', 'mp-1', { prepared: true });
    await markMerchantOrderReady('store-1', 'order-1');

    expect(detail.lines[0].product_name).toBe('Lait Vitalait 1L');
    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/orders/order-1');
    expect(apiClient.post).toHaveBeenNthCalledWith(
      1,
      '/api/merchant/stores/store-1/orders/order-1/accept',
    );
    expect(apiClient.post).toHaveBeenNthCalledWith(
      2,
      '/api/merchant/stores/store-1/orders/order-1/reject',
      { reason: 'Produit indisponible' },
    );
    expect(apiClient.post).toHaveBeenNthCalledWith(
      3,
      '/api/merchant/stores/store-1/orders/order-1/partially-accept',
      { rejected_merchant_product_ids: ['mp-1'], notes: 'Rupture.' },
    );
    expect(apiClient.post).toHaveBeenNthCalledWith(
      4,
      '/api/merchant/stores/store-1/orders/order-1/start-preparation',
    );
    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/orders/order-1/lines/mp-1/preparation',
      { prepared: true },
    );
    expect(apiClient.post).toHaveBeenNthCalledWith(
      5,
      '/api/merchant/stores/store-1/orders/order-1/mark-ready',
    );
  });
});
