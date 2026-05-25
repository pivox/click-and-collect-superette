import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantSlots,
  createMerchantSlot,
  patchMerchantSlot,
  deleteMerchantSlot,
} from '@/lib/services/merchant-slots.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const STORE_ID = '11111111-1111-4111-8111-111111111111';
const SLOT_ID = '33333333-3333-4333-8333-333333333333';

const slot = {
  id: SLOT_ID,
  starts_at: '2026-05-28T17:00:00+01:00',
  ends_at: '2026-05-28T18:00:00+01:00',
  capacity: 6,
  booked_count: 2,
  is_active: true,
};

describe('merchant slots service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists slots for a store', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: [slot] });

    const result = await listMerchantSlots(STORE_ID);

    expect(apiClient.get).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots`,
    );
    expect(result).toHaveLength(1);
    expect(result[0].booked_count).toBe(2);
  });

  it('creates a slot', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: undefined });

    await createMerchantSlot(STORE_ID, {
      starts_at: '2026-05-28T17:00:00+01:00',
      ends_at: '2026-05-28T18:00:00+01:00',
      capacity: 6,
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots`,
      {
        starts_at: '2026-05-28T17:00:00+01:00',
        ends_at: '2026-05-28T18:00:00+01:00',
        capacity: 6,
      },
    );
  });

  it('patches a slot capacity', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: undefined });

    await patchMerchantSlot(STORE_ID, SLOT_ID, { capacity: 10 });

    expect(apiClient.patch).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots/${SLOT_ID}`,
      { capacity: 10 },
    );
  });

  it('deletes a slot', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: undefined });

    await deleteMerchantSlot(STORE_ID, SLOT_ID);

    expect(apiClient.delete).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots/${SLOT_ID}`,
    );
  });
});
