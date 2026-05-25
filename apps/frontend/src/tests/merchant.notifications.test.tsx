import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantNotificationsPage from '@/app/merchant/notifications/page';
import {
  listMerchantNotifications,
  markAllMerchantNotificationsRead,
  markMerchantNotificationRead,
} from '@/lib/services/merchant-notifications.service';

vi.mock('@/lib/services/merchant-notifications.service', () => ({
  listMerchantNotifications: vi.fn(),
  markAllMerchantNotificationsRead: vi.fn(),
  markMerchantNotificationRead: vi.fn(),
}));

const unreadNotification = {
  id: 'notif-1',
  order_id: 'order-1',
  title_fr: 'Nouvelle commande',
  title_ar: 'طلب جديد',
  body_fr: 'Fatma vient de soumettre une commande pour 12:00.',
  body_ar: 'قدمت فاطمة طلبا جديدا.',
  is_read: false,
  created_at: '2026-05-25T10:00:00+01:00',
};

const readNotification = {
  id: 'notif-2',
  order_id: null,
  title_fr: 'Retrait finalisé',
  title_ar: 'تم الاستلام',
  body_fr: 'La Kadhia a été retirée.',
  body_ar: 'تم استلام القاضية.',
  is_read: true,
  created_at: '2026-05-25T09:00:00+01:00',
};

function createDeferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((promiseResolve, promiseReject) => {
    resolve = promiseResolve;
    reject = promiseReject;
  });
  return { promise, resolve, reject };
}

describe('MerchantNotificationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
    });
    vi.mocked(markMerchantNotificationRead).mockResolvedValue({
      ...unreadNotification,
      is_read: true,
    });
    vi.mocked(markAllMerchantNotificationsRead).mockResolvedValue(undefined);
  });

  it('renders merchant notifications and command link', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [unreadNotification, readNotification],
      total: 2,
      page: 1,
    });

    render(React.createElement(MerchantNotificationsPage));

    expect(screen.getByRole('heading', { name: 'Notifications' })).toBeInTheDocument();
    expect(await screen.findByText('Nouvelle commande')).toBeInTheDocument();
    expect(
      screen.getByText('Fatma vient de soumettre une commande pour 12:00.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Non lue')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /voir la commande/i })).toHaveAttribute(
      'href',
      '/merchant/commandes/order-1',
    );
    expect(screen.getByText('Retrait finalisé')).toBeInTheDocument();
  });

  it('renders empty state for all notifications', async () => {
    render(React.createElement(MerchantNotificationsPage));

    expect(await screen.findByText('Aucune notification pour le moment.')).toBeInTheDocument();
  });

  it('renders empty state for unread notifications', async () => {
    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: 'Non lues' }));

    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenLastCalledWith({ page: 1, unread: true }),
    );
    expect(await screen.findByText('Aucune notification non lue.')).toBeInTheDocument();
  });

  it('marks one notification as read and refreshes badge event', async () => {
    const refreshListener = vi.fn();
    window.addEventListener('merchant-notifications:refresh', refreshListener);
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [unreadNotification],
      total: 1,
      page: 1,
    });

    render(React.createElement(MerchantNotificationsPage));

    expect(await screen.findByText('Nouvelle commande')).toBeInTheDocument();
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [{ ...unreadNotification, is_read: true }],
      total: 1,
      page: 1,
    });

    fireEvent.click(await screen.findByRole('button', { name: /^Marquer comme lu$/i }));

    await waitFor(() => expect(markMerchantNotificationRead).toHaveBeenCalledWith('notif-1'));
    await waitFor(() => expect(refreshListener).toHaveBeenCalledTimes(1));
    expect(refreshListener).toHaveBeenCalledTimes(1);
    window.removeEventListener('merchant-notifications:refresh', refreshListener);
  });

  it('marks all notifications as read and refreshes badge event', async () => {
    const refreshListener = vi.fn();
    window.addEventListener('merchant-notifications:refresh', refreshListener);
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [unreadNotification],
      total: 1,
      page: 1,
    });

    render(React.createElement(MerchantNotificationsPage));

    expect(await screen.findByText('Nouvelle commande')).toBeInTheDocument();
    vi.mocked(listMerchantNotifications).mockResolvedValue({ items: [], total: 0, page: 1 });

    fireEvent.click(await screen.findByRole('button', { name: /tout marquer comme lu/i }));

    await waitFor(() => expect(markAllMerchantNotificationsRead).toHaveBeenCalled());
    await waitFor(() => expect(refreshListener).toHaveBeenCalledTimes(1));
    window.removeEventListener('merchant-notifications:refresh', refreshListener);
  });

  it('paginates notifications', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [readNotification],
      total: 25,
      page: 1,
    });

    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: 'Page suivante' }));

    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenLastCalledWith({ page: 2 }),
    );
  });

  it('clamps the current page after a read mutation reduces the unread total', async () => {
    vi.mocked(listMerchantNotifications)
      .mockResolvedValueOnce({
        items: [unreadNotification],
        total: 25,
        page: 1,
      })
      .mockResolvedValueOnce({
        items: [unreadNotification],
        total: 25,
        page: 1,
      })
      .mockResolvedValueOnce({
        items: [{ ...unreadNotification, id: 'notif-2' }],
        total: 25,
        page: 2,
      })
      .mockResolvedValueOnce({
        items: [],
        total: 1,
        page: 2,
      })
      .mockResolvedValueOnce({
        items: [
          {
            ...unreadNotification,
            id: 'notif-remaining',
            title_fr: 'Commande restante',
          },
        ],
        total: 1,
        page: 1,
      });

    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: 'Non lues' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Page suivante' }));
    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenLastCalledWith({ page: 2, unread: true }),
    );

    fireEvent.click(await screen.findByRole('button', { name: /^Marquer comme lu$/i }));

    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenLastCalledWith({ page: 1, unread: true }),
    );
    expect(await screen.findByText('Commande restante')).toBeInTheDocument();
    expect(screen.queryByText('Aucune notification non lue.')).not.toBeInTheDocument();
  });

  it('ignores stale list responses when filter changes quickly', async () => {
    const allRequest = createDeferred<{
      items: Array<typeof readNotification>;
      total: number;
      page: number;
    }>();
    const unreadRequest = createDeferred<{
      items: Array<typeof unreadNotification>;
      total: number;
      page: number;
    }>();

    vi.mocked(listMerchantNotifications)
      .mockReturnValueOnce(allRequest.promise)
      .mockReturnValueOnce(unreadRequest.promise);

    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: 'Non lues' }));
    unreadRequest.resolve({ items: [unreadNotification], total: 1, page: 1 });

    expect(await screen.findByText('Nouvelle commande')).toBeInTheDocument();

    allRequest.resolve({ items: [readNotification], total: 1, page: 1 });

    await waitFor(() => expect(screen.queryByText('Retrait finalisé')).not.toBeInTheDocument());
    expect(screen.getByText('Nouvelle commande')).toBeInTheDocument();
  });

  it('renders load error and retries', async () => {
    vi.mocked(listMerchantNotifications).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantNotificationsPage));

    expect(
      await screen.findByText(
        "Les notifications n'ont pas pu être chargées. Réessaie dans un instant.",
      ),
    ).toBeInTheDocument();

    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [readNotification],
      total: 1,
      page: 1,
    });
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    expect(await screen.findByText('Retrait finalisé')).toBeInTheDocument();
  });
});
