import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantOrderDetailPage from '@/app/merchant/commandes/[orderId]/page';
import {
  acceptMerchantOrder,
  getMerchantOrder,
  markMerchantOrderReady,
  partiallyAcceptMerchantOrder,
  rejectMerchantOrder,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';
import type { MerchantOrderDetail } from '@/lib/types/merchant.types';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-orders.service', () => ({
  acceptMerchantOrder: vi.fn(),
  getMerchantOrder: vi.fn(),
  markMerchantOrderReady: vi.fn(),
  partiallyAcceptMerchantOrder: vi.fn(),
  rejectMerchantOrder: vi.fn(),
  setMerchantOrderLinePrepared: vi.fn(),
  startMerchantOrderPreparation: vi.fn(),
}));

function makeOrder(status: MerchantOrderDetail['status']): MerchantOrderDetail {
  return {
    id: 'order-1',
    store_id: 'store-1',
    status,
    total_tnd: '18.500',
    pickup_slot: {
      id: 'slot-1',
      starts_at: '2026-05-24T10:00:00+01:00',
      ends_at: '2026-05-24T11:00:00+01:00',
    },
    notes: 'Sans sachet.',
    lines: [
      {
        merchant_product_id: 'mp-1',
        product_name: 'Lait Vitalait 1L',
        quantity: 2,
        unit_price_tnd: '1.700',
        line_total_tnd: '3.400',
        prepared: false,
      },
    ],
    customer_name: 'Fatma Ben Ali',
    customer_phone: '+21620111222',
    rejection_reason: null,
    created_at: '2026-05-24T08:00:00+01:00',
    updated_at: '2026-05-24T08:00:00+01:00',
  };
}

describe('MerchantOrderDetailPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows submitted actions and reloads after accept', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('submitted'))
      .mockResolvedValueOnce(makeOrder('accepted'));
    vi.mocked(acceptMerchantOrder).mockResolvedValue({ id: 'order-1', status: 'accepted' });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByRole('heading', { name: /commande order-1/i })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Accepter' }));

    await waitFor(() => expect(acceptMerchantOrder).toHaveBeenCalledWith('store-1', 'order-1'));
    expect(getMerchantOrder).toHaveBeenCalledTimes(2);
  });

  it('shows preparation action only for accepted orders', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('accepted'))
      .mockResolvedValueOnce(makeOrder('preparing'));
    vi.mocked(startMerchantOrderPreparation).mockResolvedValue({
      id: 'order-1',
      status: 'preparing',
    });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('button', { name: 'Démarrer préparation' }));

    await waitFor(() =>
      expect(startMerchantOrderPreparation).toHaveBeenCalledWith('store-1', 'order-1'),
    );
  });

  it('shows line preparation and ready action only for preparing orders', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('preparing'))
      .mockResolvedValueOnce({
        ...makeOrder('preparing'),
        lines: [{ ...makeOrder('preparing').lines[0], prepared: true }],
      })
      .mockResolvedValueOnce(makeOrder('ready'));
    vi.mocked(setMerchantOrderLinePrepared).mockResolvedValue({
      ...makeOrder('preparing'),
      lines: [{ ...makeOrder('preparing').lines[0], prepared: true }],
    });
    vi.mocked(markMerchantOrderReady).mockResolvedValue({ id: 'order-1', status: 'ready' });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByRole('button', { name: 'Commande prête' })).toBeDisabled();
    fireEvent.click(await screen.findByRole('checkbox', { name: /marquer lait vitalait 1l préparé/i }));
    await waitFor(() =>
      expect(setMerchantOrderLinePrepared).toHaveBeenCalledWith('store-1', 'order-1', 'mp-1', {
        prepared: true,
      }),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Commande prête' }));
    await waitFor(() => expect(markMerchantOrderReady).toHaveBeenCalledWith('store-1', 'order-1'));
  });

  it('shows the load failure message when the order cannot be loaded', async () => {
    vi.mocked(getMerchantOrder).mockRejectedValue(new Error('API unavailable'));

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByText('Impossible de charger cette commande.')).toBeInTheDocument();
    expect(screen.queryByText('Commande introuvable pour cette supérette.')).not.toBeInTheDocument();
  });

  it('does not expose pickup actions for ready orders', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue(makeOrder('ready'));

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByText('Commande prête pour le retrait.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /scan/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /confirmer retrait/i })).not.toBeInTheDocument();
  });

  it('shows the rejection reason for rejected orders', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue({
      ...makeOrder('rejected'),
      rejection_reason: 'Produit indisponible',
    });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByText('Commande refusée : Produit indisponible')).toBeInTheDocument();
  });

  it('shows a terminal message for cancelled orders', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue(makeOrder('cancelled'));

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByText('Commande annulée.')).toBeInTheDocument();
  });

  it('rejects a submitted order with a reason and reloads', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('submitted'))
      .mockResolvedValueOnce(makeOrder('rejected'));
    vi.mocked(rejectMerchantOrder).mockResolvedValue({ id: 'order-1', status: 'rejected' });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('button', { name: 'Refuser' }));
    expect(screen.getByRole('dialog', { name: 'Refuser la commande' })).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Motif de refus'), {
      target: { value: 'Produit indisponible' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer le refus' }));

    await waitFor(() =>
      expect(rejectMerchantOrder).toHaveBeenCalledWith('store-1', 'order-1', {
        reason: 'Produit indisponible',
      }),
    );
    expect(getMerchantOrder).toHaveBeenCalledTimes(2);
  });

  it('resets the rejection reason when the dialog reopens', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue(makeOrder('submitted'));

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('button', { name: 'Refuser' }));
    fireEvent.change(screen.getByLabelText('Motif de refus'), {
      target: { value: 'Créneau indisponible' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

    fireEvent.click(screen.getByRole('button', { name: 'Refuser' }));

    expect(screen.getByLabelText('Motif de refus')).toHaveValue('');
  });

  it('requires one accepted and one unavailable line before partial acceptance', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue({
      ...makeOrder('submitted'),
      lines: [
        makeOrder('submitted').lines[0],
        {
          merchant_product_id: 'mp-2',
          product_name: 'Eau minérale 1.5L',
          quantity: 1,
          unit_price_tnd: '0.900',
          line_total_tnd: '0.900',
          prepared: false,
        },
      ],
    });
    vi.mocked(partiallyAcceptMerchantOrder).mockResolvedValue({
      id: 'order-1',
      status: 'partially_accepted',
    });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('button', { name: 'Accepter partiellement' }));
    expect(
      screen.getByRole('dialog', { name: 'Accepter partiellement la Kadhia' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Confirmer l’acceptation partielle' })).toBeDisabled();

    fireEvent.click(screen.getByRole('checkbox', { name: /eau minérale 1.5l disponible/i }));
    fireEvent.change(screen.getByLabelText('Note pour le client'), {
      target: { value: 'Eau indisponible.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer l’acceptation partielle' }));

    await waitFor(() =>
      expect(partiallyAcceptMerchantOrder).toHaveBeenCalledWith('store-1', 'order-1', {
        rejected_merchant_product_ids: ['mp-2'],
        notes: 'Eau indisponible.',
      }),
    );
  });

  it('resets partial acceptance choices and notes when the dialog reopens', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue({
      ...makeOrder('submitted'),
      lines: [
        makeOrder('submitted').lines[0],
        {
          merchant_product_id: 'mp-2',
          product_name: 'Eau minérale 1.5L',
          quantity: 1,
          unit_price_tnd: '0.900',
          line_total_tnd: '0.900',
          prepared: false,
        },
      ],
    });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('button', { name: 'Accepter partiellement' }));
    fireEvent.click(screen.getByRole('checkbox', { name: /eau minérale 1.5l disponible/i }));
    fireEvent.change(screen.getByLabelText('Note pour le client'), {
      target: { value: 'Eau indisponible.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

    fireEvent.click(screen.getByRole('button', { name: 'Accepter partiellement' }));

    expect(screen.getByRole('checkbox', { name: /eau minérale 1.5l disponible/i })).toBeChecked();
    expect(screen.getByLabelText('Note pour le client')).toHaveValue('');
  });
});
