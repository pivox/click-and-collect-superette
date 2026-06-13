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
  it('does not render or load settings when disabled by props', async () => {
    render(<FeedbackProvider appArea="merchant" enabled={false} shopId="shop-1" />);

    await waitFor(() => expect(getCurrentFeedbackSettings).not.toHaveBeenCalled());
    expect(screen.queryByRole('button', { name: 'Retour' })).not.toBeInTheDocument();
  });

  it('does not render the Retour button when current settings are disabled', async () => {
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

  it.each([
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/merchant/login',
    '/admin/login',
    '/offline',
  ])('does not render or load settings on excluded route %s', async (excludedPathname) => {
    pathname = excludedPathname;

    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" />);

    await waitFor(() => expect(getCurrentFeedbackSettings).not.toHaveBeenCalled());
    expect(screen.queryByRole('button', { name: 'Retour' })).not.toBeInTheDocument();
  });

  it('renders the Retour button when current settings allow feedback', async () => {
    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" />);

    expect(await screen.findByRole('button', { name: 'Retour' })).toBeInTheDocument();
    expect(getCurrentFeedbackSettings).toHaveBeenCalledWith({
      appArea: 'merchant',
      appSubArea: 'merchant_commandes',
    });
  });

  it('validates empty and too short messages before submit', async () => {
    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" />);

    fireEvent.click(await screen.findByRole('button', { name: 'Retour' }));

    const dialog = await screen.findByRole('dialog', { name: 'Votre retour' });
    const submitButton = within(dialog).getByRole('button', { name: 'Envoyer' });
    expect(submitButton).toBeDisabled();

    fireEvent.change(within(dialog).getByLabelText('Message'), {
      target: { value: 'abcd' },
    });

    expect(submitButton).toBeDisabled();
    expect(createFeedback).not.toHaveBeenCalled();
    expect(within(dialog).getByLabelText('Message')).toHaveAttribute('maxLength', '2000');
  });

  it('submits the backend feedback contract and shows the success wording', async () => {
    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" locale="fr" />);

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
      expect(createFeedback).toHaveBeenCalledWith({
        type: 'bug',
        message: 'Le scan de retrait marchand échoue.',
        appArea: 'merchant',
        appSubArea: 'merchant_commandes',
        pageUrl: '/merchant/commandes',
        routeName: 'merchant_commandes',
        pageTitle: 'Commandes marchand',
        locale: 'fr',
        viewportWidth: 390,
        viewportHeight: 844,
        shopId: 'shop-1',
        contactConsent: true,
      });
    });
    const payload = vi.mocked(createFeedback).mock.calls[0][0];
    expect(Object.keys(payload).sort()).toEqual([
      'appArea',
      'appSubArea',
      'contactConsent',
      'locale',
      'message',
      'pageTitle',
      'pageUrl',
      'routeName',
      'shopId',
      'type',
      'viewportHeight',
      'viewportWidth',
    ].sort());
    expect(await within(dialog).findByText('Merci, votre retour a bien été envoyé.')).toBeInTheDocument();
  });

  it('shows a submit error when feedback creation fails', async () => {
    vi.mocked(createFeedback).mockRejectedValueOnce(new Error('Network error'));

    render(<FeedbackProvider appArea="merchant" enabled shopId="shop-1" />);

    fireEvent.click(await screen.findByRole('button', { name: 'Retour' }));

    const dialog = await screen.findByRole('dialog', { name: 'Votre retour' });
    fireEvent.change(within(dialog).getByLabelText('Message'), {
      target: { value: 'Le bouton prêt ne répond pas.' },
    });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Envoyer' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'Impossible d’envoyer votre retour. Réessayez.',
    );
  });

  it('renders Arabic wording when locale is Arabic', async () => {
    render(<FeedbackProvider appArea="client" enabled locale="ar" />);

    const button = await screen.findByRole('button', { name: 'ملاحظات' });
    fireEvent.click(button);

    const dialog = await screen.findByRole('dialog', { name: 'ملاحظتك' });
    expect(within(dialog).getByText('ملاحظتك')).toBeInTheDocument();
    expect(within(dialog).getByLabelText('نوع الملاحظة')).toBeInTheDocument();
    expect(within(dialog).getByLabelText('الرسالة')).toBeInTheDocument();
    expect(within(dialog).getByLabelText('أوافق على أن يتم التواصل معي عند الحاجة')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'إرسال' })).toBeInTheDocument();
    expect(within(dialog).getByRole('option', { name: 'فكرة' })).toBeInTheDocument();
  });
});
