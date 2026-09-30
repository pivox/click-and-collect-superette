import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

import { listSlotsForShop } from '@/lib/services/slots.service';

describe('listSlotsForShop (ORDER-LEAD-004)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('mappe les créneaux et la politique de réservation', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        store_id: 'store-1',
        items: [
          {
            id: 'slot-1',
            starts_at: '2026-10-01T09:00:00+01:00',
            ends_at: '2026-10-01T10:00:00+01:00',
            capacity: 4,
            available_count: 2,
          },
        ],
        booking_policy: {
          minimum_pickup_lead_time_minutes: 720,
          earliest_bookable_at: '2026-10-01T22:00:00+01:00',
        },
      },
    });

    const result = await listSlotsForShop('store-1', 'today');

    expect(apiClient.get).toHaveBeenCalledWith('/api/stores/store-1/pickup-slots', {
      params: { date: 'today' },
    });
    expect(result.slots).toEqual([
      {
        id: 'slot-1',
        startsAt: '2026-10-01T09:00:00+01:00',
        endsAt: '2026-10-01T10:00:00+01:00',
        capacity: 4,
        available: true,
      },
    ]);
    expect(result.bookingPolicy).toEqual({
      minimumPickupLeadTimeMinutes: 720,
      earliestBookableAt: '2026-10-01T22:00:00+01:00',
    });
  });

  it('reste compatible avec une réponse sans booking_policy', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { store_id: 'store-1', items: [] },
    });

    const result = await listSlotsForShop('store-1');

    expect(result.slots).toEqual([]);
    expect(result.bookingPolicy).toBeNull();
  });
});
