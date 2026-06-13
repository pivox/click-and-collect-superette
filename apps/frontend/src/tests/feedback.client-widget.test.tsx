import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ClientFeedbackWidget } from '@/components/feedback/ClientFeedbackWidget';

vi.mock('@/components/feedback/FeedbackProvider', () => ({
  FeedbackProvider: ({
    appArea,
    enabled,
    shopId,
  }: {
    appArea: string;
    enabled: boolean;
    shopId?: string | null;
  }) => (
    <div
      data-testid="feedback-provider"
      data-app-area={appArea}
      data-enabled={String(enabled)}
      data-shop-id={shopId ?? ''}
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

describe('ClientFeedbackWidget', () => {
  it('passes the selected supérette to client feedback', () => {
    render(<ClientFeedbackWidget />);

    const provider = screen.getByTestId('feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'client');
    expect(provider).toHaveAttribute('data-enabled', 'true');
    expect(provider).toHaveAttribute('data-shop-id', 'shop-1');
  });
});
