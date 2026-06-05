import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getAdminSubscription,
  getMerchantSubscription,
  listAdminSubscriptions,
} from '@/lib/services/subscriptions.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
  },
}));

const SUBSCRIPTION = {
  id: 'sub-1',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  lifecycle: 'active',
  pricing_phase: 'promo',
  monthly_price_tnd: '10.000',
  currency: 'TND',
  started_at: '2026-06-01T00:00:00+01:00',
  current_period_started_at: '2026-06-01T00:00:00+01:00',
  current_period_ends_at: '2026-07-01T00:00:00+01:00',
  next_phase_change_at: '2026-09-01T00:00:00+01:00',
  created_at: '2026-06-01T00:00:00+01:00',
};

describe('subscriptions service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists admin subscriptions with pagination params', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        id: 'admin-subscriptions',
        items: [SUBSCRIPTION],
        page: 2,
        limit: 10,
        total: 11,
      },
    });

    const result = await listAdminSubscriptions({ page: 2, limit: 10 });

    expect(apiClient.get).toHaveBeenCalledWith('/api/admin/subscriptions', {
      params: { page: 2, limit: 10 },
    });
    expect(result.items[0].merchant_email).toBe('ali@example.test');
  });

  it('loads an admin subscription detail', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: SUBSCRIPTION });

    const result = await getAdminSubscription('sub-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/admin/subscriptions/sub-1');
    expect(result.id).toBe('sub-1');
  });

  it('loads the current merchant subscription', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: SUBSCRIPTION });

    const result = await getMerchantSubscription();

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/subscription');
    expect(result.monthly_price_tnd).toBe('10.000');
  });
});
