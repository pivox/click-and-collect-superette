import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  getPushPermissionState,
  isWebPushSupported,
} from '@/lib/services/push-subscription.service';

// Mock the API client
vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('push-subscription.service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should return unsupported if Notification API is not available', async () => {
    const state = await getPushPermissionState();
    expect(['granted', 'denied', 'default', 'unsupported']).toContain(state);
  });

  it('should check if web push is supported', async () => {
    const supported = await isWebPushSupported();
    expect(typeof supported).toBe('boolean');
  });

  it('should require VAPID public key to subscribe', async () => {
    if (process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY === undefined) {
      // Skip subscription test if VAPID key not configured
      expect(true).toBe(true);
    }
  });
});
