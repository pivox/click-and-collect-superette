import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantSlotRules,
  createMerchantSlotRule,
  patchMerchantSlotRule,
  deleteMerchantSlotRule,
  generateMerchantSlots,
} from '@/lib/services/merchant-slot-rules.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const STORE_ID = '11111111-1111-4111-8111-111111111111';
const RULE_ID = '22222222-2222-4222-8222-222222222222';

const rule = {
  id: RULE_ID,
  weekday: 3,
  start_time: '17:00',
  end_time: '19:00',
  capacity: 6,
  is_active: true,
};

describe('merchant slot rules service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists rules for a store', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { total: 1, items: [rule] },
    });

    const result = await listMerchantSlotRules(STORE_ID);

    expect(apiClient.get).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules`,
    );
    expect(result.total).toBe(1);
    expect(result.items[0].weekday).toBe(3);
  });

  it('creates a rule', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: rule });

    const result = await createMerchantSlotRule(STORE_ID, {
      weekday: 3,
      start_time: '17:00',
      end_time: '19:00',
      capacity: 6,
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules`,
      { weekday: 3, start_time: '17:00', end_time: '19:00', capacity: 6 },
    );
    expect(result.id).toBe(RULE_ID);
  });

  it('patches a rule', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: { ...rule, capacity: 8 } });

    const result = await patchMerchantSlotRule(STORE_ID, RULE_ID, { capacity: 8 });

    expect(apiClient.patch).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/${RULE_ID}`,
      { capacity: 8 },
    );
    expect(result.capacity).toBe(8);
  });

  it('deletes a rule', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: undefined });

    await deleteMerchantSlotRule(STORE_ID, RULE_ID);

    expect(apiClient.delete).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/${RULE_ID}`,
    );
  });

  it('generates slots with default 1-month horizon', async () => {
    const generated = {
      store_id: STORE_ID,
      generated_count: 4,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25T00:00:00+01:00',
      horizon_end: '2026-06-25T00:00:00+01:00',
    };
    vi.mocked(apiClient.post).mockResolvedValue({ data: generated });

    const result = await generateMerchantSlots(STORE_ID);

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/generate`,
      { horizon_months: 1 },
    );
    expect(result.generated_count).toBe(4);
  });

  it('generates slots with explicit 3-month horizon', async () => {
    const generated = {
      store_id: STORE_ID,
      generated_count: 13,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25T00:00:00+01:00',
      horizon_end: '2026-08-25T00:00:00+01:00',
    };
    vi.mocked(apiClient.post).mockResolvedValue({ data: generated });

    const result = await generateMerchantSlots(STORE_ID, 3);

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/generate`,
      { horizon_months: 3 },
    );
    expect(result.generated_count).toBe(13);
  });
});
