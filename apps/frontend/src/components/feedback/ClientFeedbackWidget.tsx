'use client';

import { FeedbackProvider } from '@/components/feedback/FeedbackProvider';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';

export function ClientFeedbackWidget() {
  const { user, isLoading } = useClientAuth();
  const { locale } = useClientLocale();
  const { selectedStore } = useSelectedStore();

  return (
    <FeedbackProvider
      appArea="client"
      enabled={!isLoading && Boolean(user)}
      locale={locale}
      shopId={selectedStore?.id ?? null}
    />
  );
}
