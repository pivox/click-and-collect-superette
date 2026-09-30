import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getAdminPlatformSettings,
  updateAdminPlatformSettings,
} from '@/lib/services/admin/platform-settings.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    put: vi.fn(),
  },
}));

const mockGet = vi.mocked(apiClient.get);
const mockPut = vi.mocked(apiClient.put);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('admin platform settings service', () => {
  it('calls platform settings endpoints', async () => {
    mockGet.mockResolvedValue({
      data: {
        id: 'platform-settings',
        frontendOrigin: 'http://localhost:3000',
        updatedAt: '2026-06-23T13:00:00+01:00',
      },
    });
    mockPut.mockResolvedValue({
      data: {
        id: 'platform-settings',
        frontendOrigin: 'https://demo.kadhia.tn',
        updatedAt: '2026-06-23T13:01:00+01:00',
      },
    });

    await getAdminPlatformSettings();
    await updateAdminPlatformSettings({ frontendOrigin: 'https://demo.kadhia.tn' });

    expect(mockGet).toHaveBeenCalledWith('/api/admin/platform/settings');
    expect(mockPut).toHaveBeenCalledWith('/api/admin/platform/settings', {
      frontendOrigin: 'https://demo.kadhia.tn',
    });
  });
});
