import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ClientFeedbackWidget } from '@/components/feedback/ClientFeedbackWidget';

let clientAuthState = {
  user: { id: 'customer-1' } as { id: string } | null,
  isLoading: false,
};
let selectedStoreState = { id: 'shop-1', name: 'Supérette Test' } as { id: string; name: string } | null;
let clientLocaleState = 'ar';

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
  useClientAuth: () => clientAuthState,
}));

vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: () => ({
    selectedStore: selectedStoreState,
    selectStore: vi.fn(),
    clearStore: vi.fn(),
  }),
}));

vi.mock('@/lib/i18n/ClientLocaleContext', () => ({
  useClientLocale: () => ({
    locale: clientLocaleState,
    dir: clientLocaleState === 'ar' ? 'rtl' : 'ltr',
    setLocale: vi.fn(),
    t: (key: string) => key,
  }),
}));

describe('ClientFeedbackWidget', () => {
  beforeEach(() => {
    clientAuthState = {
      user: { id: 'customer-1' },
      isLoading: false,
    };
    selectedStoreState = { id: 'shop-1', name: 'Supérette Test' };
    clientLocaleState = 'ar';
  });

  it('passes the selected supérette and locale to client feedback', () => {
    render(<ClientFeedbackWidget />);

    const provider = screen.getByTestId('feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'client');
    expect(provider).toHaveAttribute('data-enabled', 'true');
    expect(provider).toHaveAttribute('data-shop-id', 'shop-1');
    expect(provider).toHaveAttribute('data-locale', 'ar');
  });

  it('disables client feedback while auth is loading', () => {
    clientAuthState = {
      user: null,
      isLoading: true,
    };

    render(<ClientFeedbackWidget />);

    expect(screen.getByTestId('feedback-provider')).toHaveAttribute('data-enabled', 'false');
  });

  it('disables client feedback when no client is authenticated', () => {
    clientAuthState = {
      user: null,
      isLoading: false,
    };

    render(<ClientFeedbackWidget />);

    expect(screen.getByTestId('feedback-provider')).toHaveAttribute('data-enabled', 'false');
  });

  it('passes no shopId when no supérette is selected', () => {
    selectedStoreState = null;
    clientLocaleState = 'fr';

    render(<ClientFeedbackWidget />);

    const provider = screen.getByTestId('feedback-provider');
    expect(provider).toHaveAttribute('data-shop-id', '');
    expect(provider).toHaveAttribute('data-locale', 'fr');
  });
});
