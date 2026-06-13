import { apiClient } from '@/lib/api';
import type {
  AdminFeedbackSettings,
  AdminFeedbackSettingsPayload,
  FeedbackAppArea,
  FeedbackEntry,
  FeedbackListResponse,
  FeedbackStatus,
  FeedbackType,
  FeedbackUserRole,
} from '@/lib/types/admin/feedbacks.types';

interface ListFeedbacksOptions {
  page?: number;
  limit?: number;
  status?: FeedbackStatus | '';
  userRole?: FeedbackUserRole | '';
  type?: FeedbackType | '';
  appArea?: FeedbackAppArea | '';
  shop?: string;
  contactConsent?: boolean | '';
  createdFrom?: string;
  createdTo?: string;
}

export async function listAdminFeedbacks(
  options: ListFeedbacksOptions = {},
): Promise<FeedbackListResponse> {
  const params = {
    page: options.page ?? 1,
    limit: options.limit ?? 20,
    ...(options.status ? { status: options.status } : {}),
    ...(options.userRole ? { userRole: options.userRole } : {}),
    ...(options.type ? { type: options.type } : {}),
    ...(options.appArea ? { appArea: options.appArea } : {}),
    ...(options.shop ? { shop: options.shop } : {}),
    ...(options.contactConsent !== undefined && options.contactConsent !== ''
      ? { contactConsent: options.contactConsent }
      : {}),
    ...(options.createdFrom ? { createdFrom: options.createdFrom } : {}),
    ...(options.createdTo ? { createdTo: options.createdTo } : {}),
  };
  const { data } = await apiClient.get<FeedbackListResponse>('/api/admin/feedbacks', { params });

  return data;
}

export async function getAdminFeedback(id: string): Promise<FeedbackEntry> {
  const { data } = await apiClient.get<FeedbackEntry>(`/api/admin/feedbacks/${id}`);

  return data;
}

export async function markAdminFeedbackRead(id: string): Promise<FeedbackEntry> {
  const { data } = await apiClient.patch<FeedbackEntry>(`/api/admin/feedbacks/${id}/read`, {});

  return data;
}

export async function markAdminFeedbackUnread(id: string): Promise<FeedbackEntry> {
  const { data } = await apiClient.patch<FeedbackEntry>(`/api/admin/feedbacks/${id}/unread`, {});

  return data;
}

export async function resolveAdminFeedback(id: string, adminNote?: string): Promise<FeedbackEntry> {
  const { data } = await apiClient.patch<FeedbackEntry>(`/api/admin/feedbacks/${id}/resolve`, {
    ...(adminNote ? { adminNote } : {}),
  });

  return data;
}

export async function reopenAdminFeedback(id: string): Promise<FeedbackEntry> {
  const { data } = await apiClient.patch<FeedbackEntry>(`/api/admin/feedbacks/${id}/reopen`, {});

  return data;
}

export async function getAdminFeedbackSettings(): Promise<AdminFeedbackSettings> {
  const { data } = await apiClient.get<AdminFeedbackSettings>('/api/admin/feedback/settings');

  return data;
}

export async function updateAdminFeedbackSettings(
  payload: AdminFeedbackSettingsPayload,
): Promise<AdminFeedbackSettings> {
  const { data } = await apiClient.put<AdminFeedbackSettings>('/api/admin/feedback/settings', payload);

  return data;
}
