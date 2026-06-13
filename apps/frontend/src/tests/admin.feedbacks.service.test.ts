import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getAdminFeedback,
  getAdminFeedbackSettings,
  listAdminFeedbacks,
  markAdminFeedbackRead,
  markAdminFeedbackUnread,
  reopenAdminFeedback,
  resolveAdminFeedback,
  updateAdminFeedbackSettings,
} from '@/lib/services/admin/feedbacks.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
  },
}));

const mockGet = vi.mocked(apiClient.get);
const mockPatch = vi.mocked(apiClient.patch);
const mockPut = vi.mocked(apiClient.put);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('admin feedbacks service', () => {
  it('lists feedbacks with admin filters', async () => {
    mockGet.mockResolvedValue({ data: { id: 'admin-feedbacks', items: [], page: 1, limit: 20, total: 0 } });

    await listAdminFeedbacks({
      page: 2,
      limit: 20,
      status: 'unread',
      userRole: 'merchant',
      type: 'bug',
      appArea: 'merchant',
      shop: 'shop-1',
      contactConsent: true,
    });

    expect(mockGet).toHaveBeenCalledWith('/api/admin/feedbacks', {
      params: {
        page: 2,
        limit: 20,
        status: 'unread',
        userRole: 'merchant',
        type: 'bug',
        appArea: 'merchant',
        shop: 'shop-1',
        contactConsent: true,
      },
    });
  });

  it('calls detail, status actions and settings endpoints', async () => {
    mockGet.mockResolvedValue({ data: { id: 'feedback-1' } });
    mockPatch.mockResolvedValue({ data: { id: 'feedback-1' } });
    mockPut.mockResolvedValue({ data: { id: 'settings-1' } });

    await getAdminFeedback('feedback-1');
    await markAdminFeedbackRead('feedback-1');
    await markAdminFeedbackUnread('feedback-1');
    await resolveAdminFeedback('feedback-1', 'Traité');
    await reopenAdminFeedback('feedback-1');
    await getAdminFeedbackSettings();
    await updateAdminFeedbackSettings({ globalEnabled: true });

    expect(mockGet).toHaveBeenCalledWith('/api/admin/feedbacks/feedback-1');
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/feedbacks/feedback-1/read', {});
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/feedbacks/feedback-1/unread', {});
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/feedbacks/feedback-1/resolve', { adminNote: 'Traité' });
    expect(mockPatch).toHaveBeenCalledWith('/api/admin/feedbacks/feedback-1/reopen', {});
    expect(mockGet).toHaveBeenCalledWith('/api/admin/feedback/settings');
    expect(mockPut).toHaveBeenCalledWith('/api/admin/feedback/settings', { globalEnabled: true });
  });
});
