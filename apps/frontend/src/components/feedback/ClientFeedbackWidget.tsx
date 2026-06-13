'use client';

import { FeedbackProvider } from '@/components/feedback/FeedbackProvider';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

export function ClientFeedbackWidget() {
  const { user, isLoading } = useClientAuth();

  return <FeedbackProvider appArea="client" enabled={!isLoading && Boolean(user)} />;
}
