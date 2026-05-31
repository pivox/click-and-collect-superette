import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MOCK_ORDER } from '@/lib/mock/orders.mock';

describe('mock Kadhia submission', () => {
  beforeEach(() => {
    vi.stubEnv('NEXT_PUBLIC_USE_MOCKS', '1');
    vi.resetModules();
    localStorage.clear();
  });

  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it('retourne une commande mock existante après soumission', async () => {
    const { submitKadhia } = await import('@/lib/services/kadhia.service');
    const { getOrder } = await import('@/lib/services/orders.service');

    const submitted = await submitKadhia({
      shopId: 'shop-el-amel',
      pickupSlotId: 'slot-demo-1',
    });

    expect(submitted).toEqual({
      orderId: MOCK_ORDER.id,
      orderCode: MOCK_ORDER.code,
    });

    await expect(getOrder(submitted.orderId)).resolves.toMatchObject({
      id: MOCK_ORDER.id,
      code: MOCK_ORDER.code,
    });
  });
});
