import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getAdminBillingDocument,
  getMerchantBillingDocument,
  listAdminBillingDocuments,
  listMerchantBillingDocuments,
  openAdminBillingDocumentWhatsappContact,
} from '@/lib/services/billing-documents.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const DOCUMENT = {
  id: 'doc-1',
  subscription_id: 'sub-1',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  document_number: 'MS-2026-000001',
  document_type: 'monthly_statement',
  document_nature_label: 'Document mensuel interne non fiscal',
  status: 'issued',
  pricing_phase: 'promo',
  currency: 'TND',
  billing_period_start: '2026-06-01T00:00:00+01:00',
  billing_period_end: '2026-07-01T00:00:00+01:00',
  issued_at: '2026-06-01T09:00:00+01:00',
  due_at: '2026-06-08T23:59:59+01:00',
  paid_at: null,
  cancelled_at: null,
  cancellation_reason: null,
  amount_tnd: '10.000',
  amount_paid_tnd: '0.000',
  amount_due_tnd: '10.000',
  reminder_schedule: [],
  whatsapp_manual_contacted_at: null,
  created_at: '2026-06-01T09:00:00+01:00',
};

describe('billing documents service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists admin billing documents with pagination params', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        id: 'admin-billing-documents',
        items: [DOCUMENT],
        page: 2,
        limit: 10,
        total: 11,
      },
    });

    const result = await listAdminBillingDocuments({ page: 2, limit: 10 });

    expect(apiClient.get).toHaveBeenCalledWith('/api/admin/billing-documents', {
      params: { page: 2, limit: 10 },
    });
    expect(result.items[0].document_number).toBe('MS-2026-000001');
  });

  it('loads admin and merchant billing document details', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: DOCUMENT });

    await expect(getAdminBillingDocument('doc-1')).resolves.toEqual(DOCUMENT);
    expect(apiClient.get).toHaveBeenCalledWith('/api/admin/billing-documents/doc-1');

    await expect(getMerchantBillingDocument('doc-1')).resolves.toEqual(DOCUMENT);
    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/billing-documents/doc-1');
  });

  it('opens an admin WhatsApp contact for a billing document', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: {
        id: 'doc-1-whatsapp',
        billing_document_id: 'doc-1',
        phone: '21620123456',
        message: 'Bonjour document MS-2026-000001',
        whatsapp_url: 'https://wa.me/21620123456?text=Bonjour',
      },
    });

    const result = await openAdminBillingDocumentWhatsappContact('doc-1');

    expect(apiClient.post).toHaveBeenCalledWith('/api/admin/billing-documents/doc-1/whatsapp-contact');
    expect(result.whatsapp_url).toBe('https://wa.me/21620123456?text=Bonjour');
  });

  it('lists merchant billing documents', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        id: 'merchant-billing-documents',
        items: [DOCUMENT],
        page: 1,
        limit: 20,
        total: 1,
      },
    });

    const result = await listMerchantBillingDocuments();

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/billing-documents', {
      params: { page: 1, limit: 20 },
    });
    expect(result.total).toBe(1);
  });
});
