import { apiClient } from '@/lib/api';
import type {
  FeedbackAppArea,
  FeedbackCreatePayload,
  FeedbackEntry,
  FeedbackSettingsCurrent,
} from '@/lib/types/feedback.types';

interface CurrentSettingsOptions {
  appArea: FeedbackAppArea;
  appSubArea?: string | null;
}

export async function getCurrentFeedbackSettings(
  options: CurrentSettingsOptions,
): Promise<FeedbackSettingsCurrent> {
  const { data } = await apiClient.get<FeedbackSettingsCurrent>('/api/feedback/settings/current', {
    params: {
      appArea: options.appArea,
      ...(options.appSubArea ? { appSubArea: options.appSubArea } : {}),
    },
    skipAuthRedirect: true,
  });

  return data;
}

export async function createFeedback(payload: FeedbackCreatePayload): Promise<FeedbackEntry> {
  const body = Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''),
  );
  const response = await apiClient.post<FeedbackEntry>('/api/feedback', body, { skipAuthRedirect: true });
  if (response.status !== 201) {
    throw new Error('FEEDBACK_UNEXPECTED_STATUS');
  }

  return response.data;
}
