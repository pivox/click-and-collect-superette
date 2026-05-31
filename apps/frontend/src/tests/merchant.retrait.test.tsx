import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantPickupPage from '@/app/merchant/retrait/page';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  redeemByCode,
  scanMerchantPickupSession,
  validateManually,
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
  redeemByCode: vi.fn(),
  validateManually: vi.fn(),
}));

const scanResult: MerchantPickupSessionScanResult = {
  id: 'session-1',
  order_id: 'order-1',
  store_id: 'store-1',
  order_number: 42,
  order_number_display: '#0042',
  status: 'pickup_pending',
  scanned_at: '2026-05-24T10:00:00+00:00',
  customer: {
    display_name: 'Haythem Mabrouk',
    first_name: 'Haythem',
    last_name: 'Mabrouk',
    phone: '+21600000000',
  },
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
  beforeEach(() => {
    vi.clearAllMocks();
  });

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
    expect(screen.getByText(/statut retrait en cours/)).toBeInTheDocument();
    expect(screen.queryByText(/statut pickup_pending/)).not.toBeInTheDocument();
  });

  it('uses the customer display name when first and last name are missing', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce({
      ...scanResult,
      customer: {
        display_name: 'Client Demo',
        first_name: null,
        last_name: null,
        phone: null,
      },
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByText('Client Demo')).toBeInTheDocument();
    expect(screen.queryByText('Client non renseigné')).not.toBeInTheDocument();
  });

  it('displays a backend error message when the scan fails', async () => {
    vi.mocked(scanMerchantPickupSession).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { detail: 'PICKUP_SESSION_TOKEN_NOT_FOUND' } },
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('PICKUP_SESSION_TOKEN_NOT_FOUND');
    expect(screen.queryByText(/Session de retrait/)).not.toBeInTheDocument();
  });

  it('displays a generic error message when the scan fails without backend detail', async () => {
    vi.mocked(scanMerchantPickupSession).mockRejectedValueOnce(new Error('Network Error'));

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "L'action n'a pas pu être effectuée. Réessaie.",
    );
    expect(screen.queryByText(/Session de retrait/)).not.toBeInTheDocument();
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

  it('keeps the current action state when a new token is invalid', async () => {
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

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: 'bad-token' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Le token QR doit être un UUID valide.',
    );
    expect(screen.getByText('Confirmation marchand enregistrée.')).toBeInTheDocument();
    expect(screen.getByText('En attente de confirmation client.')).toBeInTheDocument();
  });

  it('blocks reset and scan while a pickup mutation is pending', async () => {
    let resolveConfirm: (value: Awaited<ReturnType<typeof confirmMerchantPickupSession>>) => void;
    const pendingConfirm = new Promise<Awaited<ReturnType<typeof confirmMerchantPickupSession>>>(
      (resolve) => {
        resolveConfirm = resolve;
      },
    );

    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockReturnValueOnce(pendingConfirm);

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Scanner un autre QR' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Identifier la Kadhia' })).toBeDisabled();
    });

    resolveConfirm!({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });

    expect(await screen.findByText('Confirmation marchand enregistrée.')).toBeInTheDocument();
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

  it('resets to initial state when "Scanner un autre QR" is clicked', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    await screen.findByText('Commande #0042');

    fireEvent.click(screen.getByRole('button', { name: 'Scanner un autre QR' }));

    expect(screen.queryByText('Commande #0042')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Token QR de retrait')).toHaveValue('');
  });

  it('displays a business label after redeeming a pickup code', async () => {
    vi.mocked(redeemByCode).mockResolvedValueOnce({
      order_id: 'f055d691-1111-4111-8111-111111111111',
      status: 'completed',
    });

    render(<MerchantPickupPage />);

    fireEvent.click(screen.getByRole('button', { name: 'Code 4 chiffres' }));
    fireEvent.change(screen.getByLabelText('Code de retrait (4 chiffres)'), {
      target: { value: '1234' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Valider' }));

    expect(
      await screen.findByText('Retrait finalisé pour la commande CMD-F055D691'),
    ).toBeInTheDocument();
    expect(screen.queryByText(/— completed/)).not.toBeInTheDocument();
  });

  it('displays a business label after a manual pickup validation', async () => {
    vi.mocked(validateManually).mockResolvedValueOnce({
      id: 'validation-1',
      order_id: 'f055d691-1111-4111-8111-111111111111',
      status: 'completed',
    });

    render(<MerchantPickupPage />);

    fireEvent.click(screen.getByRole('button', { name: 'Manuel' }));
    fireEvent.change(screen.getByLabelText('Identifiant de commande (UUID)'), {
      target: { value: 'f055d691-1111-4111-8111-111111111111' },
    });
    fireEvent.change(screen.getByLabelText('Motif (obligatoire, 5 caractères minimum)'), {
      target: { value: 'Client présent sans QR.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Valider manuellement' }));

    expect(
      await screen.findByText('Retrait finalisé manuellement pour la commande CMD-F055D691'),
    ).toBeInTheDocument();
    expect(screen.queryByText(/— completed/)).not.toBeInTheDocument();
  });
});
