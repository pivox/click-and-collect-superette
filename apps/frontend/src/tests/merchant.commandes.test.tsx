import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantOrdersPage from '@/app/merchant/commandes/page';
import { formatTime } from '@/lib/format';
import {
  listMerchantOrderHistory,
  listMerchantOrders,
} from '@/lib/services/merchant-orders.service';
import type { MerchantOrderList } from '@/lib/types/merchant.types';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-orders.service', () => ({
  listMerchantOrderHistory: vi.fn(),
  listMerchantOrders: vi.fn(),
}));

describe('MerchantOrdersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchantOrders).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      limit: 20,
    });
    vi.mocked(listMerchantOrderHistory).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      limit: 20,
    });
  });

  it('renders read-only real order summaries', async () => {
    vi.mocked(listMerchantOrders).mockResolvedValue({
      items: [
        {
          id: 'order-1',
          store_id: 'store-1',
          order_number: 42,
          order_number_display: '#0042',
          status: 'submitted',
          total_tnd: '18.500',
          pickup_slot: {
            starts_at: '2026-05-23T10:00:00+01:00',
            ends_at: '2026-05-23T11:00:00+01:00',
          },
          line_count: 4,
          created_at: '2026-05-23T08:00:00+01:00',
          updated_at: '2026-05-23T08:00:00+01:00',
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantOrdersPage));

    await waitFor(() =>
      expect(listMerchantOrders).toHaveBeenCalledWith('store-1', {
        status: 'submitted,accepted,partially_accepted,preparing,ready,pickup_pending',
      }),
    );
    expect(listMerchantOrderHistory).not.toHaveBeenCalled();
    expect(screen.getByRole('heading', { name: 'Commandes' })).toBeInTheDocument();
    expect(screen.getByText('#0042')).toBeInTheDocument();
    expect(screen.getByText('Soumise')).toBeInTheDocument();
    expect(screen.getByText('18,500 TND')).toBeInTheDocument();
    expect(
      screen.getAllByText((_, node) => node?.textContent?.includes('4 produits') ?? false).length,
    ).toBeGreaterThan(0);
    const detailLink = await screen.findByRole('link', { name: /voir la commande #0042/i });
    expect(detailLink).toHaveAttribute('href', '/merchant/commandes/order-1');
  });

  it('polls active orders every 30 seconds without manual refresh', async () => {
    let intervalCallback: TimerHandler | undefined;
    const pollIntervalId = 123 as unknown as NodeJS.Timeout;
    const realSetInterval = window.setInterval;
    const realClearInterval = window.clearInterval;
    const setIntervalSpy = vi
      .spyOn(window, 'setInterval')
      .mockImplementation(((callback: TimerHandler, timeout?: number) => {
        if (timeout === 30_000) {
          intervalCallback = callback;
          return pollIntervalId;
        }

        return realSetInterval(callback, timeout) as unknown as NodeJS.Timeout;
      }) as unknown as typeof window.setInterval);
    const clearIntervalSpy = vi
      .spyOn(window, 'clearInterval')
      .mockImplementation((intervalId?: string | number | NodeJS.Timeout) => {
        if (intervalId === pollIntervalId) return;
        realClearInterval(intervalId);
      });

    vi.mocked(listMerchantOrders)
      .mockResolvedValueOnce({
        items: [],
        total: 0,
        page: 1,
        limit: 20,
      })
      .mockResolvedValueOnce({
        items: [
          {
            id: 'order-polled',
            store_id: 'store-1',
            order_number: 44,
            order_number_display: '#0044',
            status: 'submitted',
            total_tnd: '12.000',
            pickup_slot: null,
            line_count: 2,
            created_at: '2026-05-23T08:00:00+01:00',
            updated_at: '2026-05-23T08:00:00+01:00',
          },
        ],
        total: 1,
        page: 1,
        limit: 20,
      });

    const { unmount } = render(React.createElement(MerchantOrdersPage));

    await waitFor(() => expect(listMerchantOrders).toHaveBeenCalledTimes(1));
    expect(setIntervalSpy).toHaveBeenCalledWith(expect.any(Function), 30_000);

    await act(async () => {
      if (typeof intervalCallback === 'function') {
        intervalCallback();
      }
    });

    await waitFor(() => expect(listMerchantOrders).toHaveBeenCalledTimes(2));
    expect(await screen.findByText('#0044')).toBeInTheDocument();

    unmount();
    expect(clearIntervalSpy).toHaveBeenCalledWith(pollIntervalId);
    setIntervalSpy.mockRestore();
    clearIntervalSpy.mockRestore();
  });

  it('skips silent polling while the visible active orders load is pending', async () => {
    let intervalCallback: TimerHandler | undefined;
    let resolveInitialOrders!: (orders: MerchantOrderList) => void;
    const initialOrdersPromise = new Promise<MerchantOrderList>((resolve) => {
      resolveInitialOrders = resolve;
    });
    const pollIntervalId = 456 as unknown as NodeJS.Timeout;
    const realSetInterval = window.setInterval;
    const realClearInterval = window.clearInterval;
    const setIntervalSpy = vi
      .spyOn(window, 'setInterval')
      .mockImplementation(((callback: TimerHandler, timeout?: number) => {
        if (timeout === 30_000) {
          intervalCallback = callback;
          return pollIntervalId;
        }

        return realSetInterval(callback, timeout) as unknown as NodeJS.Timeout;
      }) as unknown as typeof window.setInterval);
    const clearIntervalSpy = vi
      .spyOn(window, 'clearInterval')
      .mockImplementation((intervalId?: string | number | NodeJS.Timeout) => {
        if (intervalId === pollIntervalId) return;
        realClearInterval(intervalId);
      });

    vi.mocked(listMerchantOrders).mockReturnValueOnce(initialOrdersPromise);

    const { unmount } = render(React.createElement(MerchantOrdersPage));

    await waitFor(() => expect(listMerchantOrders).toHaveBeenCalledTimes(1));

    await act(async () => {
      if (typeof intervalCallback === 'function') {
        intervalCallback();
      }
    });

    expect(listMerchantOrders).toHaveBeenCalledTimes(1);

    await act(async () => {
      resolveInitialOrders({
        items: [
          {
            id: 'order-initial',
            store_id: 'store-1',
            order_number: 44,
            order_number_display: '#0044',
            status: 'submitted',
            total_tnd: '12.000',
            pickup_slot: null,
            line_count: 2,
            created_at: '2026-05-23T08:00:00+01:00',
            updated_at: '2026-05-23T08:00:00+01:00',
          },
        ],
        total: 1,
        page: 1,
        limit: 20,
      });
      await initialOrdersPromise;
    });

    expect(await screen.findByText('#0044')).toBeInTheDocument();

    unmount();
    expect(clearIntervalSpy).toHaveBeenCalledWith(pollIntervalId);
    setIntervalSpy.mockRestore();
    clearIntervalSpy.mockRestore();
  });

  it('keeps the latest active orders response when an older poll resolves later', async () => {
    let intervalCallback: TimerHandler | undefined;
    let resolveFirstPoll!: (orders: MerchantOrderList) => void;
    const firstPollPromise = new Promise<MerchantOrderList>((resolve) => {
      resolveFirstPoll = resolve;
    });
    const pollIntervalId = 789 as unknown as NodeJS.Timeout;
    const realSetInterval = window.setInterval;
    const realClearInterval = window.clearInterval;
    const setIntervalSpy = vi
      .spyOn(window, 'setInterval')
      .mockImplementation(((callback: TimerHandler, timeout?: number) => {
        if (timeout === 30_000) {
          intervalCallback = callback;
          return pollIntervalId;
        }

        return realSetInterval(callback, timeout) as unknown as NodeJS.Timeout;
      }) as unknown as typeof window.setInterval);
    const clearIntervalSpy = vi
      .spyOn(window, 'clearInterval')
      .mockImplementation((intervalId?: string | number | NodeJS.Timeout) => {
        if (intervalId === pollIntervalId) return;
        realClearInterval(intervalId);
      });

    vi.mocked(listMerchantOrders)
      .mockResolvedValueOnce({
        items: [],
        total: 0,
        page: 1,
        limit: 20,
      })
      .mockReturnValueOnce(firstPollPromise)
      .mockResolvedValueOnce({
        items: [
          {
            id: 'order-fresh',
            store_id: 'store-1',
            order_number: 45,
            order_number_display: '#0045',
            status: 'submitted',
            total_tnd: '13.000',
            pickup_slot: null,
            line_count: 1,
            created_at: '2026-05-23T08:01:00+01:00',
            updated_at: '2026-05-23T08:01:00+01:00',
          },
        ],
        total: 1,
        page: 1,
        limit: 20,
      });

    const { unmount } = render(React.createElement(MerchantOrdersPage));

    await waitFor(() => expect(listMerchantOrders).toHaveBeenCalledTimes(1));
    await screen.findByText('Aucune commande dans ce filtre.');

    await act(async () => {
      if (typeof intervalCallback === 'function') {
        intervalCallback();
      }
    });

    await waitFor(() => expect(listMerchantOrders).toHaveBeenCalledTimes(2));

    await act(async () => {
      if (typeof intervalCallback === 'function') {
        intervalCallback();
      }
    });

    await waitFor(() => expect(listMerchantOrders).toHaveBeenCalledTimes(3));
    expect(await screen.findByText('#0045')).toBeInTheDocument();

    await act(async () => {
      resolveFirstPoll({
        items: [
          {
            id: 'order-stale',
            store_id: 'store-1',
            order_number: 44,
            order_number_display: '#0044',
            status: 'submitted',
            total_tnd: '12.000',
            pickup_slot: null,
            line_count: 2,
            created_at: '2026-05-23T08:00:00+01:00',
            updated_at: '2026-05-23T08:00:00+01:00',
          },
        ],
        total: 1,
        page: 1,
        limit: 20,
      });
      await firstPollPromise;
    });

    expect(screen.queryByText('#0044')).not.toBeInTheDocument();
    expect(screen.getByText('#0045')).toBeInTheDocument();

    unmount();
    expect(clearIntervalSpy).toHaveBeenCalledWith(pollIntervalId);
    setIntervalSpy.mockRestore();
    clearIntervalSpy.mockRestore();
  });

  it('loads history with pickup statuses when the merchant opens Historique', async () => {
    vi.mocked(listMerchantOrderHistory).mockResolvedValue({
      items: [
        {
          id: 'order-ready-1',
          order_number: 43,
          order_number_display: '#0043',
          status: 'ready',
          status_label_fr: 'Prête',
          status_label_ar: 'جاهزة',
          customer: {
            first_name: 'Fatma',
            last_name: 'Ben Ali',
            phone: '+21620111222',
          },
          total: '42.300',
          pickup_slot: {
            starts_at: '2026-05-24T12:00:00+01:00',
            ends_at: '2026-05-24T12:30:00+01:00',
          },
          created_at: '2026-05-24T09:00:00+01:00',
          updated_at: '2026-05-24T11:45:00+01:00',
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });
    const pickupTime = formatTime('2026-05-24T12:00:00+01:00');
    const updatedTime = formatTime('2026-05-24T11:45:00+01:00');

    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));

    await waitFor(() =>
      expect(listMerchantOrderHistory).toHaveBeenCalledWith('store-1', {
        page: 1,
        limit: 20,
        status: 'ready,pickup_pending',
      }),
    );

    expect(screen.getByRole('tab', { name: 'À retirer' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Clôturées' })).toBeInTheDocument();
    expect(screen.getByText('#0043')).toBeInTheDocument();
    expect(screen.getByText('Prête')).toBeInTheDocument();
    expect(screen.getByText('42,300 TND')).toBeInTheDocument();
    expect(screen.getByText('Fatma Ben Ali')).toBeInTheDocument();
    expect(
      screen.getAllByText(
        (_, node) => node?.textContent?.includes(`rendez-vous ${pickupTime}`) ?? false,
      ).length,
    ).toBeGreaterThan(0);
    expect(
      screen.getAllByText(
        (_, node) => node?.textContent?.includes(`mis à jour ${updatedTime}`) ?? false,
      ).length,
    ).toBeGreaterThan(0);
    expect(
      screen.getByRole('link', { name: /voir la commande #0043/i }),
    ).toHaveAttribute('href', '/merchant/commandes/order-ready-1');
  });

  it('switches history to closed statuses', async () => {
    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));
    await waitFor(() => expect(listMerchantOrderHistory).toHaveBeenCalledTimes(1));

    fireEvent.click(await screen.findByRole('tab', { name: 'Clôturées' }));

    await waitFor(() =>
      expect(listMerchantOrderHistory).toHaveBeenLastCalledWith('store-1', {
        page: 1,
        limit: 20,
        status: 'completed,cancelled,rejected',
      }),
    );
  });

  it('paginates merchant order history', async () => {
    vi.mocked(listMerchantOrderHistory).mockResolvedValue({
      items: [
        {
          id: 'order-ready-page-1',
          status: 'ready',
          status_label_fr: 'Prête',
          status_label_ar: 'جاهزة',
          customer: {
            first_name: 'Fatma',
            last_name: 'Ben Ali',
            phone: '+21620111222',
          },
          total: '42.300',
          pickup_slot: {
            starts_at: '2026-05-24T12:00:00+01:00',
            ends_at: '2026-05-24T12:30:00+01:00',
          },
          created_at: '2026-05-24T09:00:00+01:00',
          updated_at: '2026-05-24T11:45:00+01:00',
        },
      ],
      total: 25,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));
    await waitFor(() => expect(listMerchantOrderHistory).toHaveBeenCalledTimes(1));

    fireEvent.click(await screen.findByRole('button', { name: 'Page suivante' }));

    await waitFor(() =>
      expect(listMerchantOrderHistory).toHaveBeenLastCalledWith('store-1', {
        page: 2,
        limit: 20,
        status: 'ready,pickup_pending',
      }),
    );
  });

  it('renders an empty history state', async () => {
    vi.mocked(listMerchantOrderHistory).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));

    expect(await screen.findByText('Aucune commande dans cet historique.')).toBeInTheDocument();
  });

  it('renders a dedicated history error', async () => {
    vi.mocked(listMerchantOrderHistory).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));

    expect(
      await screen.findByText("Impossible de charger l'historique des commandes."),
    ).toBeInTheDocument();
  });

  it('hides stale history rows when a reload fails', async () => {
    vi.mocked(listMerchantOrderHistory)
      .mockResolvedValueOnce({
        items: [
          {
            id: 'order-ready-stale',
            status: 'ready',
            status_label_fr: 'Prête',
            status_label_ar: 'جاهزة',
            customer: {
              first_name: 'Fatma',
              last_name: 'Ben Ali',
              phone: '+21620111222',
            },
            total: '42.300',
            pickup_slot: null,
            created_at: '2026-05-24T09:00:00+01:00',
            updated_at: '2026-05-24T11:45:00+01:00',
          },
        ],
        total: 1,
        page: 1,
        limit: 20,
      })
      .mockRejectedValueOnce(new Error('Network error'));

    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));
    expect(await screen.findByText('order-ready-stale')).toBeInTheDocument();

    fireEvent.click(await screen.findByRole('tab', { name: 'Clôturées' }));

    const errorMessage = await screen.findByText("Impossible de charger l'historique des commandes.");
    expect(errorMessage).toBeInTheDocument();
    expect(screen.queryByText('order-ready-stale')).not.toBeInTheDocument();
    expect(within(errorMessage.closest('div') as HTMLElement).getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });

  it('resets the history page when returning to the history tab', async () => {
    vi.mocked(listMerchantOrderHistory).mockResolvedValue({
      items: [
        {
          id: 'order-ready-page-reset',
          status: 'ready',
          status_label_fr: 'Prête',
          status_label_ar: 'جاهزة',
          customer: {
            first_name: 'Fatma',
            last_name: 'Ben Ali',
            phone: '+21620111222',
          },
          total: '42.300',
          pickup_slot: null,
          created_at: '2026-05-24T09:00:00+01:00',
          updated_at: '2026-05-24T11:45:00+01:00',
        },
      ],
      total: 25,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantOrdersPage));

    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));
    await waitFor(() => expect(listMerchantOrderHistory).toHaveBeenCalledTimes(1));

    fireEvent.click(await screen.findByRole('button', { name: 'Page suivante' }));
    await waitFor(() =>
      expect(listMerchantOrderHistory).toHaveBeenLastCalledWith('store-1', {
        page: 2,
        limit: 20,
        status: 'ready,pickup_pending',
      }),
    );

    fireEvent.click(await screen.findByRole('tab', { name: 'Actives' }));
    fireEvent.click(await screen.findByRole('tab', { name: 'Historique' }));

    await waitFor(() =>
      expect(listMerchantOrderHistory).toHaveBeenLastCalledWith('store-1', {
        page: 1,
        limit: 20,
        status: 'ready,pickup_pending',
      }),
    );
  });
});
