import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantOrderDetailPage from '@/app/merchant/commandes/[orderId]/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';
import { getMerchantOrder } from '@/lib/services/merchant-orders.service';
import { prepareMerchantOrderWhatsappContact } from '@/lib/services/order-whatsapp.service';
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

vi.mock('@/lib/services/order-whatsapp.service', () => ({
  prepareMerchantOrderWhatsappContact: vi.fn(),
}));

function makeOrder(): MerchantOrderDetail {
  return {
    id: 'order-1',
    store_id: 'store-1',
    order_number: 42,
    order_number_display: '#0042',
    status: 'preparing',
    total_tnd: '18.500',
    pickup_slot: null,
    notes: null,
    lines: [],
    customer_name: 'Fatma Ben Ali',
    customer_phone: '+21620111222',
    rejection_reason: null,
    created_at: '2026-05-24T08:00:00+01:00',
    updated_at: '2026-05-24T08:00:00+01:00',
  };
}

function renderPage() {
  return render(
    <MerchantLocaleProvider>
      <MerchantOrderDetailPage params={{ orderId: 'order-1' }} />
    </MerchantLocaleProvider>,
  );
}

describe('MerchantOrderDetailPage — bouton WhatsApp', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getMerchantOrder).mockResolvedValue(makeOrder());
  });

  it('affiche le bouton et ouvre le lien wa.me préparé au clic', async () => {
    const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
    vi.mocked(prepareMerchantOrderWhatsappContact).mockResolvedValue({
      phone: '21620111222',
      message: 'Bonjour, ici Supérette Ezzahra au sujet de votre commande #0042.',
      whatsapp_url: 'https://wa.me/21620111222?text=Bonjour',
    });

    renderPage();

    const button = await screen.findByRole('button', {
      name: /Contacter le client sur WhatsApp/i,
    });
    fireEvent.click(button);

    await waitFor(() =>
      expect(prepareMerchantOrderWhatsappContact).toHaveBeenCalledWith('store-1', 'order-1'),
    );
    await waitFor(() =>
      expect(openSpy).toHaveBeenCalledWith(
        'https://wa.me/21620111222?text=Bonjour',
        '_blank',
        'noopener',
      ),
    );
    openSpy.mockRestore();
  });

  it('affiche un message discret quand le téléphone client est indisponible (409)', async () => {
    const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
    vi.mocked(prepareMerchantOrderWhatsappContact).mockRejectedValue(
      Object.assign(new Error('HTTP 409'), { response: { status: 409 } }),
    );

    renderPage();

    fireEvent.click(
      await screen.findByRole('button', { name: /Contacter le client sur WhatsApp/i }),
    );

    expect(
      await screen.findByText(/numéro WhatsApp du client n'est pas disponible/i),
    ).toBeTruthy();
    expect(openSpy).not.toHaveBeenCalled();
    openSpy.mockRestore();
  });
});
