import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantNotifications,
  markAllMerchantNotificationsRead,
  markMerchantNotificationRead,
} from '@/lib/services/merchant-notifications.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

describe('merchant notifications service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists all merchant notifications on page 1 by default', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1 },
    });

    const result = await listMerchantNotifications();

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/notifications', {
      params: { page: 1 },
    });
    expect(result).toEqual({ items: [], total: 0, page: 1 });
  });

  it('lists unread merchant notifications with explicit page', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        items: [
          {
            id: 'notif-1',
            order_id: 'order-1',
            title_fr: 'Nouvelle commande',
            title_ar: 'طلب جديد',
            body_fr: 'Fatma vient de soumettre une commande.',
            body_ar: 'قدمت فاطمة طلبا جديدا.',
            is_read: false,
            created_at: '2026-05-25T10:00:00+01:00',
          },
        ],
        total: 1,
        page: 2,
      },
    });

    const result = await listMerchantNotifications({ page: 2, unread: true });

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/notifications', {
      params: { page: 2, unread: true },
    });
    expect(result.items[0].title_fr).toBe('Nouvelle commande');
  });

  it('marks one merchant notification as read', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: {
        id: 'notif-1',
        order_id: 'order-1',
        title_fr: 'Nouvelle commande',
        title_ar: 'طلب جديد',
        body_fr: 'Fatma vient de soumettre une commande.',
        body_ar: 'قدمت فاطمة طلبا جديدا.',
        is_read: true,
        created_at: '2026-05-25T10:00:00+01:00',
      },
    });

    const result = await markMerchantNotificationRead('notif-1');

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/notifications/notif-1/read',
      {},
    );
    expect(result.is_read).toBe(true);
  });

  it('marks all merchant notifications as read', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: undefined });

    await markAllMerchantNotificationsRead();

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/notifications/read-all',
      {},
    );
  });
});
