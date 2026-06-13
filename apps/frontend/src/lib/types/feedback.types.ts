export type FeedbackStatus = 'unread' | 'read' | 'resolved';
export type FeedbackType = 'bug' | 'idea' | 'confusing' | 'other';
export type FeedbackAppArea = 'client' | 'merchant' | 'admin';
export type FeedbackUserRole = 'client' | 'merchant' | 'admin';

export interface FeedbackSettingsCurrent {
  id: string;
  enabled: boolean;
  appArea: FeedbackAppArea;
  appSubArea: string | null;
  requireAuthenticatedUser: boolean;
}

export interface FeedbackUser {
  id: string;
  email?: string;
  name?: string;
}

export interface FeedbackShop {
  id: string;
  name?: string;
}

export interface FeedbackEntry {
  id: string;
  status: FeedbackStatus;
  type: FeedbackType;
  message?: string;
  messageExcerpt?: string;
  appArea: FeedbackAppArea;
  appSubArea: string | null;
  userRole?: FeedbackUserRole;
  user: FeedbackUser;
  shop: FeedbackShop | null;
  pageUrl?: string | null;
  routeName?: string | null;
  pageTitle?: string | null;
  locale?: string | null;
  viewportWidth?: number | null;
  viewportHeight?: number | null;
  userAgent?: string | null;
  contactConsent: boolean;
  adminNote?: string | null;
  readAt?: string | null;
  resolvedAt?: string | null;
  createdAt: string;
  updatedAt?: string;
}

export interface FeedbackCreatePayload {
  type: FeedbackType;
  message: string;
  appArea: FeedbackAppArea;
  appSubArea?: string | null;
  pageUrl?: string | null;
  routeName?: string | null;
  pageTitle?: string | null;
  locale?: string | null;
  viewportWidth?: number | null;
  viewportHeight?: number | null;
  shopId?: string | null;
  contactConsent: boolean;
}
