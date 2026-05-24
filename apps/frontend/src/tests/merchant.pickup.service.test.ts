import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  scanMerchantPickupSession,
} from '@/lib/services/merchant-pickup.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
    patch: vi.fn(),
  },
}));

describe('merchant pickup service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('scans a pickup session token', async () => {
    vi.mocked(apiClient.post).mockResolvedValueOnce({
      data: { id: 'session-1', status: 'pickup_pending' },
    });

    await expect(
      scanMerchantPickupSession('11111111-1111-4111-8111-111111111111'),
    ).resolves.toEqual({
      id: 'session-1',
      status: 'pickup_pending',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/merchant/pickup-sessions/scan', {
      token: '11111111-1111-4111-8111-111111111111',
    });
  });

  it('confirms a pickup session with an empty JSON body', async () => {
    vi.mocked(apiClient.patch).mockResolvedValueOnce({
      data: { id: 'session-1', order_status: 'pickup_pending' },
    });

    await confirmMerchantPickupSession('session-1');

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/pickup-sessions/session-1/confirm',
      {},
    );
  });

  it('force completes a pickup session with a note', async () => {
    vi.mocked(apiClient.patch).mockResolvedValueOnce({
      data: { id: 'session-1', order_status: 'completed', force_note: 'Client parti.' },
    });

    await forceCompleteMerchantPickupSession('session-1', 'Client parti.');

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/pickup-sessions/session-1/force-complete',
      { note: 'Client parti.' },
    );
  });
});
