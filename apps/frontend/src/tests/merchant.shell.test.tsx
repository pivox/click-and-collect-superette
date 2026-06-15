import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantShell } from '@/components/merchant/MerchantShell';
import { listMerchantNotifications } from '@/lib/services/merchant-notifications.service';
import { listMerchantSlots } from '@/lib/services/merchant-slots.service';

let pathname = '/merchant';

const merchantAuthContext = {
  merchant: {
    email: 'marchand@kadhia.tn',
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  } as { email: string; store: { id: string; name: string; active: boolean } } | null,
  logout: vi.fn(),
};

vi.mock('next/navigation', () => ({
  usePathname: () => pathname,
}));

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantAuthContext,
}));

vi.mock('@/lib/services/merchant-notifications.service', () => ({
  listMerchantNotifications: vi.fn(),
}));

vi.mock('@/lib/services/merchant-slots.service', () => ({
  listMerchantSlots: vi.fn(),
}));

vi.mock('@/lib/i18n/MerchantLocaleContext', () => ({
  useMerchantLocale: () => ({
    locale: 'ar',
    dir: 'rtl',
    setLocale: vi.fn(),
    t: (key: string) => key,
  }),
}));

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
      data-testid="merchant-feedback-provider"
      data-app-area={appArea}
      data-enabled={String(enabled)}
      data-shop-id={shopId ?? ''}
      data-locale={locale ?? ''}
    />
  ),
}));

function createDeferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((promiseResolve, promiseReject) => {
    resolve = promiseResolve;
    reject = promiseReject;
  });
  return { promise, resolve, reject };
}

describe('MerchantShell', () => {
  beforeEach(() => {
    pathname = '/merchant';
    merchantAuthContext.merchant = {
      email: 'marchand@kadhia.tn',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    };
    vi.clearAllMocks();
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
    });
    vi.mocked(listMerchantSlots).mockResolvedValue([]);
  });

  it('renders active merchant navigation including the settings link', async () => {
    render(
      React.createElement(
        MerchantShell,
        null,
        React.createElement('p', null, 'Contenu marchand'),
      ),
    );

    expect(screen.getAllByText('Supérette Ezzahra')).toHaveLength(2);
    expect(screen.getByText('marchand@kadhia.tn')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /Dashboard/i })[0]).toHaveAttribute(
      'href',
      '/merchant',
    );
    expect(screen.getAllByRole('link', { name: /Commandes/i })[0]).toHaveAttribute(
      'href',
      '/merchant/commandes',
    );
    expect(screen.getAllByRole('link', { name: /Notifications/i })[0]).toHaveAttribute(
      'href',
      '/merchant/notifications',
    );
    expect(screen.getAllByRole('link', { name: /Créneaux/i })[0]).toHaveAttribute(
      'href',
      '/merchant/creneaux',
    );
    expect(screen.getAllByRole('link', { name: /Catalogue/i })[0]).toHaveAttribute(
      'href',
      '/merchant/catalogue',
    );
    expect(screen.getAllByRole('link', { name: /Paramètres/i })[0]).toHaveAttribute(
      'href',
      '/merchant/parametres',
    );
    expect(screen.getByText('Contenu marchand')).toBeInTheDocument();

    expect(await screen.findByText('Contenu marchand')).toBeInTheDocument();
    expect(listMerchantNotifications).toHaveBeenCalledWith({ unread: true });
  });

  it('passes the active merchant locale to feedback', () => {
    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    const provider = screen.getByTestId('merchant-feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'merchant');
    expect(provider).toHaveAttribute('data-enabled', 'true');
    expect(provider).toHaveAttribute('data-shop-id', 'store-1');
    expect(provider).toHaveAttribute('data-locale', 'ar');
  });

  it('disables merchant feedback on the merchant login route', () => {
    pathname = '/merchant/login';

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Connexion')));

    const provider = screen.getByTestId('merchant-feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'merchant');
    expect(provider).toHaveAttribute('data-enabled', 'false');
    expect(provider).toHaveAttribute('data-shop-id', 'store-1');
    expect(provider).toHaveAttribute('data-locale', 'ar');
    expect(listMerchantSlots).not.toHaveBeenCalled();
  });

  it('disables merchant feedback when no merchant is authenticated', () => {
    merchantAuthContext.merchant = null;

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(screen.getByTestId('merchant-feedback-provider')).toHaveAttribute('data-enabled', 'false');
  });

  it('renders Catalogue as the active merchant navigation link', async () => {
    pathname = '/merchant/catalogue/produits';

    render(
      React.createElement(
        MerchantShell,
        null,
        React.createElement('p', null, 'Contenu catalogue'),
      ),
    );

    const catalogueLinks = screen.getAllByRole('link', { name: /Catalogue/i });

    expect(catalogueLinks.length).toBeGreaterThanOrEqual(2);
    expect(catalogueLinks.every((link) => link.getAttribute('href') === '/merchant/catalogue')).toBe(
      true,
    );
    expect(
      catalogueLinks.some(
        (link) =>
          link.classList.contains('bg-white/10') && link.classList.contains('font-semibold'),
      ),
    ).toBe(true);
    expect(catalogueLinks.some((link) => link.classList.contains('bg-primary'))).toBe(true);
    expect(screen.queryByRole('button', { name: /Catalogue/i })).not.toBeInTheDocument();
    expect(screen.getByText('Contenu catalogue')).toBeInTheDocument();
  });

  it('shows unread notification badge when unread total is greater than zero', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 3,
      page: 1,
    });

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    await waitFor(() =>
      expect(screen.getAllByLabelText('3 notifications non lues')).toHaveLength(2),
    );
  });

  it('does not show unread notification badge when unread total is zero', async () => {
    const { container } = render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(await screen.findByText('Page')).toBeInTheDocument();
    expect(screen.queryByLabelText(/notifications non lues/i)).not.toBeInTheDocument();

    const badges = Array.from(container.querySelectorAll('[aria-live="polite"][aria-atomic="true"]'));
    expect(badges).toHaveLength(2);
    expect(badges.every((badge) => !badge.hasAttribute('aria-hidden'))).toBe(true);
    expect(badges.every((badge) => badge.textContent === '')).toBe(true);
    expect(badges.every((badge) => !badge.className.includes('hidden'))).toBe(true);
  });

  it('refreshes unread notification badge when the refresh event is dispatched', async () => {
    vi.mocked(listMerchantNotifications)
      .mockResolvedValueOnce({ items: [], total: 1, page: 1 })
      .mockResolvedValueOnce({ items: [], total: 0, page: 1 });

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    await waitFor(() =>
      expect(screen.getAllByLabelText('1 notification non lue')).toHaveLength(2),
    );

    window.dispatchEvent(new Event('merchant-notifications:refresh'));

    expect(await screen.findByText('Page')).toBeInTheDocument();
    expect(screen.queryByLabelText(/notification non lue/i)).not.toBeInTheDocument();
  });

  it('ignores stale unread badge responses', async () => {
    const initialRequest = createDeferred<{ items: []; total: number; page: number }>();
    const refreshRequest = createDeferred<{ items: []; total: number; page: number }>();
    vi.mocked(listMerchantNotifications)
      .mockReturnValueOnce(initialRequest.promise)
      .mockReturnValueOnce(refreshRequest.promise);

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    window.dispatchEvent(new Event('merchant-notifications:refresh'));
    refreshRequest.resolve({ items: [], total: 2, page: 1 });

    await waitFor(() =>
      expect(screen.getAllByLabelText('2 notifications non lues')).toHaveLength(2),
    );

    initialRequest.resolve({ items: [], total: 5, page: 1 });

    await waitFor(() =>
      expect(screen.getAllByLabelText('2 notifications non lues')).toHaveLength(2),
    );
    expect(screen.queryByLabelText('5 notifications non lues')).not.toBeInTheDocument();
  });

  it('caps large unread notification badge values', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 150,
      page: 1,
    });

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    await waitFor(() =>
      expect(screen.getAllByLabelText('150 notifications non lues')).toHaveLength(2),
    );
    expect(screen.getAllByText('99+')).toHaveLength(2);
  });

  it('keeps rendering the shell when unread badge loading fails', async () => {
    vi.mocked(listMerchantNotifications).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(await screen.findByText('Page')).toBeInTheDocument();
    expect(screen.queryByLabelText(/notifications non lues/i)).not.toBeInTheDocument();
  });
});
