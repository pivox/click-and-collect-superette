import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  createAdminSubscriptionPayment,
  listAdminSubscriptionPayments,
  listMerchantSubscriptionPayments,
} from '@/lib/services/subscription-payments.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const PAYMENT = {
  id: 'payment-1',
  billing_document_id: 'doc-1',
  subscription_id: 'sub-1',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  amount_tnd: '10.000',
  currency: 'TND',
  method: 'cash',
  reference: 'Caisse',
  status: 'confirmed',
  paid_at: '2026-06-04T10:30:00+01:00',
  period_start: '2026-06-01T00:00:00+01:00',
  period_end: '2026-07-01T00:00:00+01:00',
  validated_by_admin_id: 'admin-1',
  validated_at: '2026-06-04T10:35:00+01:00',
};

describe('subscription payments service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('creates an admin manual subscription payment', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: PAYMENT });

    const result = await createAdminSubscriptionPayment({
      billing_document_id: 'doc-1',
      amount_tnd: '10.000',
      method: 'cash',
      paid_at: '2026-06-04T10:30:00+01:00',
      reference: 'Caisse',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/admin/subscription-payments', {
      billing_document_id: 'doc-1',
      amount_tnd: '10.000',
      method: 'cash',
      paid_at: '2026-06-04T10:30:00+01:00',
      reference: 'Caisse',
    });
    expect(result.status).toBe('confirmed');
  });

  it('lists admin and merchant payments', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        id: 'subscription-payments',
        items: [PAYMENT],
        page: 1,
        limit: 20,
        total: 1,
      },
    });

    await listAdminSubscriptionPayments();
    expect(apiClient.get).toHaveBeenCalledWith('/api/admin/subscription-payments', {
      params: { page: 1, limit: 20 },
    });

    await listMerchantSubscriptionPayments();
    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/subscription-payments', {
      params: { page: 1, limit: 20 },
    });
  });
});
