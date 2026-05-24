import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import MerchantPickupPage from '@/app/merchant/retrait/page';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  scanMerchantPickupSession,
} from '@/lib/services/merchant-pickup.service';
import type { MerchantPickupSessionScanResult } from '@/lib/types/merchant.types';

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      store: { id: 'store-1', name: 'Supérette Test', active: true },
      email: 'merchant@example.test',
    },
  }),
}));

vi.mock('@/lib/services/merchant-pickup.service', () => ({
  scanMerchantPickupSession: vi.fn(),
  confirmMerchantPickupSession: vi.fn(),
  forceCompleteMerchantPickupSession: vi.fn(),
}));

const scanResult: MerchantPickupSessionScanResult = {
  id: 'session-1',
  order_id: 'order-1',
  store_id: 'store-1',
  order_number: '#0042',
  status: 'pickup_pending',
  scanned_at: '2026-05-24T10:00:00+00:00',
  customer: { first_name: 'Haythem', last_name: 'Mabrouk', phone: '+21600000000' },
  lines: [
    {
      merchant_product_id: 'product-1',
      name: 'Lait Vitalait 1L',
      quantity: 2,
      unit_price_tnd: '2.800',
    },
  ],
};

describe('MerchantPickupPage', () => {
  it('blocks an invalid token before calling the API', async () => {
    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: 'not-a-uuid' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByText('Le token QR doit être un UUID valide.')).toBeInTheDocument();
    expect(scanMerchantPickupSession).not.toHaveBeenCalled();
  });

  it('scans a token and displays the pickup session', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByText('Commande #0042')).toBeInTheDocument();
    expect(screen.getByText('Haythem Mabrouk')).toBeInTheDocument();
    expect(screen.getByText('Lait Vitalait 1L')).toBeInTheDocument();
  });

  it('confirms the merchant handoff and shows waiting customer state', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));

    expect(await screen.findByText('Confirmation marchand enregistrée.')).toBeInTheDocument();
    expect(screen.getByText('En attente de confirmation client.')).toBeInTheDocument();
  });

  it('requires a force completion note', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Forcer la finalisation' }));

    expect(
      await screen.findByText('La note est obligatoire pour forcer la finalisation.'),
    ).toBeInTheDocument();
    expect(forceCompleteMerchantPickupSession).not.toHaveBeenCalled();
  });

  it('force completes with a note', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });
    vi.mocked(forceCompleteMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'completed',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: true,
      is_completed: true,
      force_completed_by_merchant: true,
      force_note: 'Client parti sans confirmer.',
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));
    fireEvent.change(await screen.findByLabelText('Note de finalisation forcée'), {
      target: { value: 'Client parti sans confirmer.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Forcer la finalisation' }));

    await waitFor(() => {
      expect(forceCompleteMerchantPickupSession).toHaveBeenCalledWith(
        'session-1',
        'Client parti sans confirmer.',
      );
    });
    expect(await screen.findByText('Retrait finalisé.')).toBeInTheDocument();
  });
});
