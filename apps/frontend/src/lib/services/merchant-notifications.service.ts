import { apiClient } from '@/lib/api';
import type {
  MerchantNotificationList,
  MerchantNotificationListOptions,
  MerchantNotificationReadResult,
} from '@/lib/types/merchant.types';

export async function listMerchantNotifications(
  options: MerchantNotificationListOptions = {},
): Promise<MerchantNotificationList> {
  const { data } = await apiClient.get<MerchantNotificationList>(
    '/api/merchant/notifications',
    {
      params: {
        page: options.page ?? 1,
        ...(options.unread ? { unread: true } : {}),
      },
    },
  );
  return data;
}

export async function markMerchantNotificationRead(
  notificationId: string,
): Promise<MerchantNotificationReadResult> {
  const { data } = await apiClient.patch<MerchantNotificationReadResult>(
    `/api/merchant/notifications/${notificationId}/read`,
    {},
  );
  return data;
}

export async function markAllMerchantNotificationsRead(): Promise<void> {
  await apiClient.patch('/api/merchant/notifications/read-all', {});
}
