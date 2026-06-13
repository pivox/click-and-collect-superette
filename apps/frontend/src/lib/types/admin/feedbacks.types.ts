import type {
  FeedbackAppArea,
  FeedbackEntry,
  FeedbackStatus,
  FeedbackType,
  FeedbackUserRole,
} from '@/lib/types/feedback.types';

export type {
  FeedbackAppArea,
  FeedbackEntry,
  FeedbackStatus,
  FeedbackType,
  FeedbackUserRole,
};

export interface FeedbackListResponse {
  id: string;
  items: FeedbackEntry[];
  page: number;
  limit: number;
  total: number;
}

export interface AdminFeedbackSettings {
  id: string;
  globalEnabled: boolean;
  clientEnabled: boolean;
  merchantEnabled: boolean;
  adminEnabled: boolean;
  clientAreaEnabled: boolean;
  merchantAreaEnabled: boolean;
  adminAreaEnabled: boolean;
  requireAuthenticatedUser: boolean;
  updatedAt: string;
}

export interface AdminFeedbackSettingsPayload {
  globalEnabled?: boolean;
  clientEnabled?: boolean;
  merchantEnabled?: boolean;
  adminEnabled?: boolean;
  clientAreaEnabled?: boolean;
  merchantAreaEnabled?: boolean;
  adminAreaEnabled?: boolean;
  requireAuthenticatedUser?: boolean;
}
