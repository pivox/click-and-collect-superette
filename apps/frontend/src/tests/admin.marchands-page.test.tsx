import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MarchandsPage from '@/app/admin/marchands/page';
import {
  getMerchant,
  listMerchants,
} from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';

vi.mock('@/lib/services/admin/merchants.service', () => ({
  listMerchants: vi.fn(),
  getMerchant: vi.fn(),
  createMerchant: vi.fn(),
  updateMerchant: vi.fn(),
  suspendMerchant: vi.fn(),
  activateMerchant: vi.fn(),
}));

const MERCHANT: Merchant = {
  id: 'merchant-1',
  email: 'ali@example.test',
  first_name: 'Ali',
  last_name: 'Ben Salah',
  phone: '+21600000001',
  is_active: true,
  subscription_lifecycle: null,
  created_at: '2026-06-01T10:00:00+01:00',
  stores_count: 2,
};

const SECOND_MERCHANT: Merchant = {
  id: 'merchant-2',
  email: 'noura@example.test',
  first_name: 'Noura',
  last_name: 'Kacem',
  phone: '+21600000002',
  is_active: true,
  subscription_lifecycle: null,
  created_at: '2026-06-02T10:00:00+01:00',
  stores_count: 1,
};

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((innerResolve) => {
    resolve = innerResolve;
  });

  return { promise, resolve };
}

describe('MarchandsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchants).mockResolvedValue({
      id: 'admin-merchants',
      items: [MERCHANT],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getMerchant).mockResolvedValue({
      ...MERCHANT,
      ops_journal: {
        overdue_orders_count: 3,
        cancelled_orders_count: 2,
        last_activity_at: '2026-06-05T11:15:00+01:00',
        last_activity_status: 'cancelled',
      },
    });
  });

  it('affiche le journal opérationnel dans la fiche marchand admin', async () => {
    render(<MarchandsPage />);

    expect(await screen.findByText('Ali Ben Salah')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Modifier/i }));

    await waitFor(() => {
      expect(getMerchant).toHaveBeenCalledWith(MERCHANT.id);
    });

    const journal = (await screen.findByText('Journal opérationnel')).closest('section');
    expect(journal).not.toBeNull();
    expect(within(journal!).getByText('Retards')).toBeInTheDocument();
    expect(within(journal!).getByText('3')).toBeInTheDocument();
    expect(within(journal!).getByText('Annulations')).toBeInTheDocument();
    expect(within(journal!).getByText('2')).toBeInTheDocument();
    expect(within(journal!).getByText('Dernière activité')).toBeInTheDocument();
    expect(within(journal!).getByText('Annulée')).toBeInTheDocument();
  });

  it('affiche le statut de suspension abonnement dans la fiche marchand admin', async () => {
    vi.mocked(listMerchants).mockResolvedValue({
      id: 'admin-merchants',
      items: [{ ...MERCHANT, subscription_lifecycle: 'suspended' }],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getMerchant).mockResolvedValue({
      ...MERCHANT,
      subscription_lifecycle: 'suspended',
      ops_journal: {
        overdue_orders_count: 0,
        cancelled_orders_count: 0,
        last_activity_at: null,
        last_activity_status: null,
      },
    });

    render(<MarchandsPage />);

    expect(await screen.findByText('Abonnement suspendu')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Modifier/i }));

    expect(await screen.findByText('Suspension douce abonnement')).toBeInTheDocument();
    expect(screen.getByText('Les nouvelles Kadhia sont bloquées, le catalogue et l’historique restent conservés.')).toBeInTheDocument();
  });

  it('ignore les réponses détail marchand obsolètes', async () => {
    const firstDetail = deferred<typeof MERCHANT>();
    const secondDetail = deferred<typeof SECOND_MERCHANT>();
    vi.mocked(listMerchants).mockResolvedValue({
      id: 'admin-merchants',
      items: [MERCHANT, SECOND_MERCHANT],
      page: 1,
      limit: 20,
      total: 2,
    });
    vi.mocked(getMerchant)
      .mockReturnValueOnce(firstDetail.promise)
      .mockReturnValueOnce(secondDetail.promise);

    render(<MarchandsPage />);

    const editButtons = await screen.findAllByRole('button', { name: /Modifier/i });
    fireEvent.click(editButtons[0]);
    fireEvent.click(editButtons[1]);

    await act(async () => {
      secondDetail.resolve({
        ...SECOND_MERCHANT,
        ops_journal: {
          overdue_orders_count: 0,
          cancelled_orders_count: 0,
          last_activity_at: null,
          last_activity_status: null,
        },
      });
    });

    await waitFor(() => {
      expect(screen.getByDisplayValue('Noura')).toBeInTheDocument();
    });

    await act(async () => {
      firstDetail.resolve({
        ...MERCHANT,
        ops_journal: {
          overdue_orders_count: 3,
          cancelled_orders_count: 2,
          last_activity_at: '2026-06-05T11:15:00+01:00',
          last_activity_status: 'cancelled',
        },
      });
    });

    await waitFor(() => {
      expect(screen.getByDisplayValue('Noura')).toBeInTheDocument();
      expect(screen.queryByDisplayValue('Ali')).not.toBeInTheDocument();
    });
  });
});
