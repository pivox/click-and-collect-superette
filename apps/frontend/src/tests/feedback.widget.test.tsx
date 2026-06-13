import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { FeedbackProvider } from '@/components/feedback/FeedbackProvider';
import {
  createFeedback,
  getCurrentFeedbackSettings,
} from '@/lib/services/feedback.service';

let pathname = '/merchant/commandes';

vi.mock('next/navigation', () => ({
  usePathname: () => pathname,
}));

vi.mock('@/lib/services/feedback.service', () => ({
  getCurrentFeedbackSettings: vi.fn(),
  createFeedback: vi.fn(),
}));

beforeEach(() => {
  vi.clearAllMocks();
  pathname = '/merchant/commandes';
  document.title = 'Commandes marchand';
  Object.defineProperty(window, 'innerWidth', { configurable: true, value: 390 });
  Object.defineProperty(window, 'innerHeight', { configurable: true, value: 844 });
  vi.mocked(getCurrentFeedbackSettings).mockResolvedValue({
    id: 'feedback-current-settings',
    enabled: true,
    appArea: 'merchant',
    appSubArea: 'merchant_commandes',
    requireAuthenticatedUser: true,
  });
  vi.mocked(createFeedback).mockResolvedValue({
    id: 'feedback-1',
    status: 'unread',
    type: 'bug',
    appArea: 'merchant',
    appSubArea: 'merchant_commandes',
    user: { id: 'user-1' },
    shop: { id: 'shop-1' },
    contactConsent: true,
    createdAt: '2026-06-13T12:00:00+01:00',
  });
});

describe('FeedbackProvider', () => {
  it('does not render the Retour button when disabled', async () => {
    vi.mocked(getCurrentFeedbackSettings).mockResolvedValueOnce({
      id: 'feedback-current-settings',
      enabled: false,
      appArea: 'merchant',
      appSubArea: 'merchant_commandes',
      requireAuthenticatedUser: true,
    });

    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" />);

    await waitFor(() => expect(getCurrentFeedbackSettings).toHaveBeenCalled());
    expect(screen.queryByRole('button', { name: 'Retour' })).not.toBeInTheDocument();
  });

  it('submits feedback and shows the success wording', async () => {
    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" />);

    const button = await screen.findByRole('button', { name: 'Retour' });
    fireEvent.click(button);

    const dialog = await screen.findByRole('dialog', { name: 'Votre retour' });
    fireEvent.change(within(dialog).getByLabelText('Type de retour'), {
      target: { value: 'bug' },
    });
    fireEvent.change(within(dialog).getByLabelText('Message'), {
      target: { value: 'Le scan de retrait marchand échoue.' },
    });
    fireEvent.click(within(dialog).getByLabelText('J’accepte d’être recontacté si besoin'));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Envoyer' }));

    await waitFor(() => {
      expect(createFeedback).toHaveBeenCalledWith(expect.objectContaining({
        type: 'bug',
        message: 'Le scan de retrait marchand échoue.',
        appArea: 'merchant',
        appSubArea: 'merchant_commandes',
        pageUrl: '/merchant/commandes',
        pageTitle: 'Commandes marchand',
        viewportWidth: 390,
        viewportHeight: 844,
        shopId: 'shop-1',
        contactConsent: true,
      }));
    });
    expect(await within(dialog).findByText('Merci, votre retour a bien été envoyé.')).toBeInTheDocument();
  });
});
