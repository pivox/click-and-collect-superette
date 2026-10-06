import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { getAdminMessengerMonitoring } from '@/lib/services/admin/ops.service';

vi.mock('@/lib/api', () => ({ apiClient: { get: vi.fn() } }));

describe('Messenger monitoring response', () => {
  beforeEach(() => vi.clearAllMocks());

  it('normalizes omitted nullable fields for an empty queue', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: {
      status: 'ok', pending: 0, failed: 0,
      thresholds: { pending: 100, oldest_age_s: 900 },
      checked_at: '2026-10-03T10:00:00+01:00',
    } });

    expect(await getAdminMessengerMonitoring()).toMatchObject({
      oldest_age_s: null, last_consumed_at: null,
    });
  });

  it('preserves a zero-second age and worker activity', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: {
      status: 'ok', pending: 1, failed: 0, oldest_age_s: 0,
      last_consumed_at: '2026-10-03T09:59:00+01:00',
      thresholds: { pending: 100, oldest_age_s: 900 },
      checked_at: '2026-10-03T10:00:00+01:00',
    } });

    expect(await getAdminMessengerMonitoring()).toMatchObject({
      oldest_age_s: 0, last_consumed_at: '2026-10-03T09:59:00+01:00',
    });
  });
});
