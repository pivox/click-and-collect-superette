import { apiClient } from '@/lib/api';
import type {
  AdminPlatformSettings,
  AdminPlatformSettingsPayload,
} from '@/lib/types/admin/platform-settings.types';

export async function getAdminPlatformSettings(): Promise<AdminPlatformSettings> {
  const { data } = await apiClient.get<AdminPlatformSettings>('/api/admin/platform/settings');

  return data;
}

export async function updateAdminPlatformSettings(
  payload: AdminPlatformSettingsPayload,
): Promise<AdminPlatformSettings> {
  const { data } = await apiClient.put<AdminPlatformSettings>('/api/admin/platform/settings', payload);

  return data;
}
