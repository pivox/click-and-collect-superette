import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: { post: vi.fn() },
}));

// Disable mocks so the real API path is exercised
vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

import {
  prepareCustomerOrderWhatsappContact,
  prepareMerchantOrderWhatsappContact,
} from '@/lib/services/order-whatsapp.service';

const RAW_CONTACT = {
  id: 'order-uuid-1-whatsapp',
  order_id: 'order-uuid-1',
  phone: '21620123456',
  message: 'Bonjour, je vous contacte au sujet de ma commande #0042 chez Supérette El Amen.',
  whatsapp_url: 'https://wa.me/21620123456?text=Bonjour',
};

describe('order whatsapp service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('POSTs the customer whatsapp-contact endpoint and returns the prepared contact', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: RAW_CONTACT });

    const contact = await prepareCustomerOrderWhatsappContact('order-uuid-1');

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/me/orders/order-uuid-1/whatsapp-contact',
      {},
    );
    expect(contact).toEqual({
      phone: '21620123456',
      message: RAW_CONTACT.message,
      whatsapp_url: RAW_CONTACT.whatsapp_url,
    });
  });

  it('POSTs the merchant whatsapp-contact endpoint and returns the prepared contact', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { ...RAW_CONTACT, message: 'Bonjour, ici Supérette El Amen au sujet de votre commande #0042.' },
    });

    const contact = await prepareMerchantOrderWhatsappContact('store-uuid-1', 'order-uuid-1');

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/merchant/stores/store-uuid-1/orders/order-uuid-1/whatsapp-contact',
      {},
    );
    expect(contact.phone).toBe('21620123456');
    expect(contact.message).toContain('votre commande #0042');
    expect(contact.whatsapp_url).toBe(RAW_CONTACT.whatsapp_url);
  });

  it('propagates API errors (e.g. 409 missing phone) to the caller', async () => {
    const error = Object.assign(new Error('HTTP 409'), { response: { status: 409 } });
    vi.mocked(apiClient.post).mockRejectedValue(error);

    await expect(prepareCustomerOrderWhatsappContact('order-uuid-1')).rejects.toBe(error);
  });
});
