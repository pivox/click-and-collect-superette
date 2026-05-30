import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn() },
}));

// Disable mocks so the real API path is exercised
vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

import { getOrder, projectTimeline } from '@/lib/services/orders.service';
import type { Order } from '@/types';

function makeAxiosError(status: number) {
  return Object.assign(new Error(`HTTP ${status}`), {
    response: { status },
  });
}

const RAW_ORDER = {
  id: 'order-uuid-1',
  kadhia_id: 'kadhia-uuid-1',
  store_id: 'store-uuid-1',
  store_name: 'Supérette El Amen',
  store_address: 'Rue de la Liberté',
  store_city: 'Tunis',
  status: 'ready',
  total_tnd: '12.500',
  pickup_slot_id: 'slot-uuid-1',
  pickup_slot: {
    id: 'slot-uuid-1',
    starts_at: '2026-05-28T10:00:00+01:00',
    ends_at: '2026-05-28T10:30:00+01:00',
  },
  notes: null,
  lines: [],
  created_at: '2026-05-28T10:00:00Z',
  updated_at: '2026-05-28T10:00:00Z',
};

describe('getOrder', () => {
  beforeEach(() => vi.clearAllMocks());

  it('retourne un Order mappé sur une réponse 200', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: RAW_ORDER });
    const order = await getOrder('order-uuid-1');
    expect(order).not.toBeNull();
    expect(order!.id).toBe('order-uuid-1');
    expect(order!.status).toBe('ready');
    expect(order!.totalAmountTnd).toBe('12.500');
    expect(order!.shopName).toBe('Supérette El Amen');
    expect(order!.pickupSlot).toMatchObject({
      id: 'slot-uuid-1',
      startsAt: '2026-05-28T10:00:00+01:00',
      endsAt: '2026-05-28T10:30:00+01:00',
    });
    expect(apiClient.get).toHaveBeenCalledWith('/api/me/orders/order-uuid-1');
  });

  it('retourne null sur une réponse 404', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(makeAxiosError(404));
    const order = await getOrder('order-uuid-missing');
    expect(order).toBeNull();
  });

  it('propage l\'erreur sur une réponse 401', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(makeAxiosError(401));
    await expect(getOrder('order-uuid-1')).rejects.toMatchObject({
      response: { status: 401 },
    });
  });

  it('propage l\'erreur sur une réponse 403', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(makeAxiosError(403));
    await expect(getOrder('order-uuid-1')).rejects.toMatchObject({
      response: { status: 403 },
    });
  });

  it('propage l\'erreur sur une réponse 500', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(makeAxiosError(500));
    await expect(getOrder('order-uuid-1')).rejects.toMatchObject({
      response: { status: 500 },
    });
  });

  it('propage l\'erreur réseau (pas de response.status)', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(new Error('Network Error'));
    await expect(getOrder('order-uuid-1')).rejects.toThrow('Network Error');
  });
});

describe('projectTimeline', () => {
  function makeOrder(status: Order['status']): Order {
    return {
      id: 'o1',
      shopId: 's1',
      status,
      totalAmountTnd: '0.000',
      pickupSlot: null,
      submittedAt: null,
      acceptedAt: null,
      readyAt: null,
      completedAt: null,
      rejectionReason: null,
      code: 'CMD-O1',
      customerNote: null,
      lines: [],
    };
  }

  it('marque la première étape comme current sur submitted', () => {
    const steps = projectTimeline(makeOrder('submitted'));
    expect(steps[0].state).toBe('current');
    expect(steps.slice(1).every((s) => s.state === 'todo')).toBe(true);
  });

  it('marque les 4 premières étapes done et la dernière current sur completed', () => {
    const steps = projectTimeline(makeOrder('completed'));
    expect(steps.slice(0, 4).every((s) => s.state === 'done')).toBe(true);
    expect(steps[4].state).toBe('current');
  });

  it('retourne un tableau de 5 étapes', () => {
    expect(projectTimeline(makeOrder('accepted'))).toHaveLength(5);
  });

  it('traite rejected comme un état hors-timeline (toutes à todo)', () => {
    const steps = projectTimeline(makeOrder('rejected'));
    expect(steps.every((s) => s.state === 'todo')).toBe(true);
  });

  it('traite partially_accepted comme submitted (index 0 current)', () => {
    const steps = projectTimeline(makeOrder('partially_accepted'));
    expect(steps[0].state).toBe('current');
  });
});
