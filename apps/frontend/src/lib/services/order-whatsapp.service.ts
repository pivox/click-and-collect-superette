import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

/**
 * S14-005 — semi-manual WhatsApp contact around an order.
 * The backend prepares a prefilled wa.me link (customer→merchant or
 * merchant→customer) and records an admin audit trail entry; nothing is
 * sent automatically.
 */
export interface OrderWhatsappContact {
  phone: string;
  message: string;
  whatsapp_url: string;
}

interface RawOrderWhatsappContact {
  id: string;
  order_id: string;
  phone: string;
  message: string;
  whatsapp_url: string;
}

function mockContact(message: string): OrderWhatsappContact {
  return {
    phone: '21620123456',
    message,
    whatsapp_url: `https://wa.me/21620123456?text=${encodeURIComponent(message)}`,
  };
}

/** Customer → merchant: POST /api/me/orders/{orderId}/whatsapp-contact. */
export async function prepareCustomerOrderWhatsappContact(
  orderId: string,
): Promise<OrderWhatsappContact> {
  if (USE_MOCKS) {
    return mockDelay(
      mockContact(`Bonjour, je vous contacte au sujet de ma commande ${orderId} chez Supérette.`),
    );
  }
  const { data } = await apiClient.post<RawOrderWhatsappContact>(
    `/api/me/orders/${orderId}/whatsapp-contact`,
    {},
  );
  return { phone: data.phone, message: data.message, whatsapp_url: data.whatsapp_url };
}

/** Merchant → customer: POST /api/merchant/stores/{storeId}/orders/{orderId}/whatsapp-contact. */
export async function prepareMerchantOrderWhatsappContact(
  storeId: string,
  orderId: string,
): Promise<OrderWhatsappContact> {
  if (USE_MOCKS) {
    return mockDelay(
      mockContact(`Bonjour, ici Supérette au sujet de votre commande ${orderId}.`),
    );
  }
  const { data } = await apiClient.post<RawOrderWhatsappContact>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/whatsapp-contact`,
    {},
  );
  return { phone: data.phone, message: data.message, whatsapp_url: data.whatsapp_url };
}
