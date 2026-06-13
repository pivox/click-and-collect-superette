import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ClientFeedbackWidget } from '@/components/feedback/ClientFeedbackWidget';

vi.mock('@/components/feedback/FeedbackProvider', () => ({
  FeedbackProvider: ({
    appArea,
    enabled,
    shopId,
    locale,
  }: {
    appArea: string;
    enabled: boolean;
    shopId?: string | null;
    locale?: string;
  }) => (
    <div
      data-testid="feedback-provider"
      data-app-area={appArea}
      data-enabled={String(enabled)}
      data-shop-id={shopId ?? ''}
      data-locale={locale ?? ''}
    />
  ),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: () => ({ user: { id: 'customer-1' }, isLoading: false }),
}));

vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: () => ({
    selectedStore: { id: 'shop-1', name: 'Supérette Test' },
    selectStore: vi.fn(),
    clearStore: vi.fn(),
  }),
}));

vi.mock('@/lib/i18n/ClientLocaleContext', () => ({
  useClientLocale: () => ({
    locale: 'ar',
    dir: 'rtl',
    setLocale: vi.fn(),
    t: (key: string) => key,
  }),
}));

describe('ClientFeedbackWidget', () => {
  it('passes the selected supérette and locale to client feedback', () => {
    render(<ClientFeedbackWidget />);

    const provider = screen.getByTestId('feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'client');
    expect(provider).toHaveAttribute('data-enabled', 'true');
    expect(provider).toHaveAttribute('data-shop-id', 'shop-1');
    expect(provider).toHaveAttribute('data-locale', 'ar');
  });
});
