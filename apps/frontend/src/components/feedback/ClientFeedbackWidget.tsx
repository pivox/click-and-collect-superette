'use client';

import { FeedbackProvider } from '@/components/feedback/FeedbackProvider';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';

export function ClientFeedbackWidget() {
  const { user, isLoading } = useClientAuth();
  const { selectedStore } = useSelectedStore();

  return (
    <FeedbackProvider
      appArea="client"
      enabled={!isLoading && Boolean(user)}
      shopId={selectedStore?.id ?? null}
    />
  );
}
