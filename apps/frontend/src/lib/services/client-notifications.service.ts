import { apiClient } from "@/lib/api";
import {
  MOCK_CLIENT_NOTIFICATIONS,
  type ClientNotificationItem,
} from "@/lib/mock/client-notifications.mock";
import { USE_MOCKS, mockDelay } from "./index";

export type { ClientNotificationItem };

export interface ClientNotificationList {
  items: ClientNotificationItem[];
  total: number;
  page: number;
}

export interface ClientNotificationListOptions {
  page?: number;
  unread?: boolean;
}

let mockNotifications = [...MOCK_CLIENT_NOTIFICATIONS];

export async function listClientNotifications(
  options: ClientNotificationListOptions = {},
): Promise<ClientNotificationList> {
  if (USE_MOCKS) {
    const items = options.unread
      ? mockNotifications.filter((n) => !n.is_read)
      : mockNotifications;
    return mockDelay({ items, total: items.length, page: options.page ?? 1 });
  }
  const { data } = await apiClient.get<ClientNotificationList>(
    "/api/me/notifications",
    { params: { page: options.page ?? 1, ...(options.unread ? { unread: true } : {}) } },
  );
  return data;
}

export async function markClientNotificationRead(id: string): Promise<ClientNotificationItem> {
  if (USE_MOCKS) {
    mockNotifications = mockNotifications.map((n) =>
      n.id === id ? { ...n, is_read: true } : n,
    );
    const updated = mockNotifications.find((n) => n.id === id)!;
    window.dispatchEvent(new Event("client-notifications:refresh"));
    return mockDelay(updated);
  }
  const { data } = await apiClient.patch<ClientNotificationItem>(
    `/api/me/notifications/${id}/read`,
    {},
  );
  return data;
}

export async function markAllClientNotificationsRead(): Promise<void> {
  if (USE_MOCKS) {
    mockNotifications = mockNotifications.map((n) => ({ ...n, is_read: true }));
    window.dispatchEvent(new Event("client-notifications:refresh"));
    return mockDelay(undefined);
  }
  await apiClient.patch("/api/me/notifications/read-all", {});
}
