import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  notFound: vi.fn(),
}));

vi.mock('@/lib/services', () => ({
  getOrder: vi.fn(),
  prepareCustomerOrderWhatsappContact: vi.fn(),
  projectTimeline: vi.fn(() => [
    { key: 'submitted', label: 'Commande envoyée', state: 'current' },
  ]),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import OrderTrackingPage from '@/app/(client)/orders/[orderId]/page';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { getOrder, prepareCustomerOrderWhatsappContact } from '@/lib/services';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';
import type { Order } from '@/types';

const ORDER: Order = {
  id: 'order-uuid-1',
  shopId: 'store-uuid-1',
  shopName: 'Supérette El Amen',
  shopAddress: 'Rue de la Liberté',
  shopCity: 'Tunis',
  status: 'submitted',
  totalAmountTnd: '12.500',
  pickupSlot: null,
  submittedAt: null,
  acceptedAt: null,
  readyAt: null,
  completedAt: null,
  rejectionReason: null,
  code: '#0042',
  customerNote: null,
  lines: [],
};

function renderPage() {
  return render(
    <ClientLocaleProvider>
      <OrderTrackingPage params={{ orderId: 'order-uuid-1' }} />
    </ClientLocaleProvider>,
  );
}

describe('OrderTrackingPage — bouton WhatsApp', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useClientAuth).mockReturnValue({
      user: { token: 'tok', email: 'client@test.com', name: 'Client Test' },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
    vi.mocked(getOrder).mockResolvedValue(ORDER);
  });

  it('affiche le bouton et ouvre le lien wa.me préparé au clic', async () => {
    const assignSpy = vi.spyOn(window.location, 'assign').mockImplementation(() => {});
    vi.mocked(prepareCustomerOrderWhatsappContact).mockResolvedValue({
      phone: '21620123456',
      message: 'Bonjour, je vous contacte au sujet de ma commande #0042 chez Supérette El Amen.',
      whatsapp_url: 'https://wa.me/21620123456?text=Bonjour',
    });

    renderPage();

    const button = await screen.findByRole('button', {
      name: /Contacter la supérette sur WhatsApp/i,
    });
    fireEvent.click(button);

    await waitFor(() =>
      expect(prepareCustomerOrderWhatsappContact).toHaveBeenCalledWith('order-uuid-1'),
    );
    await waitFor(() =>
      expect(assignSpy).toHaveBeenCalledWith('https://wa.me/21620123456?text=Bonjour'),
    );
    assignSpy.mockRestore();
  });

  it('affiche un message discret quand le téléphone est indisponible (409)', async () => {
    const assignSpy = vi.spyOn(window.location, 'assign').mockImplementation(() => {});
    vi.mocked(prepareCustomerOrderWhatsappContact).mockRejectedValue(
      Object.assign(new Error('HTTP 409'), { response: { status: 409 } }),
    );

    renderPage();

    fireEvent.click(
      await screen.findByRole('button', { name: /Contacter la supérette sur WhatsApp/i }),
    );

    expect(
      await screen.findByText(/numéro WhatsApp de la supérette n'est pas disponible/i),
    ).toBeTruthy();
    expect(assignSpy).not.toHaveBeenCalled();
    assignSpy.mockRestore();
  });
});
