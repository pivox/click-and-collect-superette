import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantShell } from '@/components/merchant/MerchantShell';
import { listMerchantNotifications } from '@/lib/services/merchant-notifications.service';

let pathname = '/merchant';

vi.mock('next/navigation', () => ({
  usePathname: () => pathname,
}));

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      email: 'marchand@kadhia.tn',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    },
    logout: vi.fn(),
  }),
}));

vi.mock('@/lib/services/merchant-notifications.service', () => ({
  listMerchantNotifications: vi.fn(),
}));

describe('MerchantShell', () => {
  beforeEach(() => {
    pathname = '/merchant';
    vi.clearAllMocks();
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
    });
  });

  it('renders active merchant navigation and disabled future sections', async () => {
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
    expect(screen.getByRole('button', { name: /Créneaux/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Catalogue/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Paramètres/i })).toBeDisabled();
    expect(screen.getByText('Contenu marchand')).toBeInTheDocument();

    expect(await screen.findByText('Contenu marchand')).toBeInTheDocument();
    expect(listMerchantNotifications).toHaveBeenCalledWith({ unread: true });
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
    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(await screen.findByText('Page')).toBeInTheDocument();
    expect(screen.queryByLabelText(/notifications non lues/i)).not.toBeInTheDocument();
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

  it('keeps rendering the shell when unread badge loading fails', async () => {
    vi.mocked(listMerchantNotifications).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(await screen.findByText('Page')).toBeInTheDocument();
    expect(screen.queryByLabelText(/notifications non lues/i)).not.toBeInTheDocument();
  });
});
