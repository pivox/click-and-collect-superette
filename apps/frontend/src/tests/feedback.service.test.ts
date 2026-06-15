import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  createFeedback,
  getCurrentFeedbackSettings,
} from '@/lib/services/feedback.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const mockGet = vi.mocked(apiClient.get);
const mockPost = vi.mocked(apiClient.post);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('feedback service', () => {
  it('reads current settings with app area and sub area', async () => {
    mockGet.mockResolvedValue({
      data: {
        id: 'feedback-current-settings',
        enabled: true,
        appArea: 'merchant',
        appSubArea: 'merchant_orders',
        requireAuthenticatedUser: true,
      },
    });

    const result = await getCurrentFeedbackSettings({
      appArea: 'merchant',
      appSubArea: 'merchant_orders',
    });

    expect(mockGet).toHaveBeenCalledWith('/api/feedback/settings/current', {
      params: { appArea: 'merchant', appSubArea: 'merchant_orders' },
      skipAuthRedirect: true,
    });
    expect(result.enabled).toBe(true);
  });

  it('posts a feedback payload with page and viewport context', async () => {
    mockPost.mockResolvedValue({
      data: {
        id: 'feedback-1',
        status: 'unread',
        type: 'bug',
        appArea: 'client',
        appSubArea: 'client_kadhia',
        user: { id: 'user-1' },
        shop: null,
        contactConsent: true,
        createdAt: '2026-06-13T12:00:00+01:00',
      },
    });

    await createFeedback({
      type: 'bug',
      message: 'Le bouton Kadhia ne répond pas.',
      appArea: 'client',
      appSubArea: 'client_kadhia',
      pageUrl: '/kadhia',
      routeName: 'client_kadhia',
      pageTitle: 'Ma Kadhia',
      locale: 'fr',
      viewportWidth: 390,
      viewportHeight: 844,
      contactConsent: true,
    });

    expect(mockPost).toHaveBeenCalledWith('/api/feedback', {
      type: 'bug',
      message: 'Le bouton Kadhia ne répond pas.',
      appArea: 'client',
      appSubArea: 'client_kadhia',
      pageUrl: '/kadhia',
      routeName: 'client_kadhia',
      pageTitle: 'Ma Kadhia',
      locale: 'fr',
      viewportWidth: 390,
      viewportHeight: 844,
      contactConsent: true,
    }, { skipAuthRedirect: true });
  });
});
