import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MarchandsPage from '@/app/admin/marchands/page';
import {
  getMerchant,
  listMerchants,
} from '@/lib/services/admin/merchants.service';

vi.mock('@/lib/services/admin/merchants.service', () => ({
  listMerchants: vi.fn(),
  getMerchant: vi.fn(),
  createMerchant: vi.fn(),
  updateMerchant: vi.fn(),
  suspendMerchant: vi.fn(),
  activateMerchant: vi.fn(),
}));

const MERCHANT = {
  id: 'merchant-1',
  email: 'ali@example.test',
  first_name: 'Ali',
  last_name: 'Ben Salah',
  phone: '+21600000001',
  is_active: true,
  created_at: '2026-06-01T10:00:00+01:00',
  stores_count: 2,
};

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
});
