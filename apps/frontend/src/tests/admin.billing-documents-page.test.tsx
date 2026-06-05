import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminBillingDocumentsPage from '@/app/admin/facturation/page';
import {
  getAdminBillingDocument,
  listAdminBillingDocuments,
  openAdminBillingDocumentWhatsappContact,
} from '@/lib/services/billing-documents.service';
import { createAdminSubscriptionPayment } from '@/lib/services/subscription-payments.service';
import type { BillingDocument } from '@/lib/types/billing-documents.types';

vi.mock('@/lib/services/billing-documents.service', () => ({
  listAdminBillingDocuments: vi.fn(),
  getAdminBillingDocument: vi.fn(),
  openAdminBillingDocumentWhatsappContact: vi.fn(),
}));

vi.mock('@/lib/services/subscription-payments.service', () => ({
  createAdminSubscriptionPayment: vi.fn(),
}));

const DOCUMENT: BillingDocument = {
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
  reminder_schedule: [
    {
      stage: 'j_minus_7',
      label: 'J-7',
      scheduled_at: '2026-06-01T23:59:59+01:00',
      email_status: 'sent',
      email_sent_at: '2026-06-01T09:00:00+01:00',
      email_failed_at: null,
      whatsapp_contacted_at: null,
    },
    {
      stage: 'j_minus_3',
      label: 'J-3',
      scheduled_at: '2026-06-05T23:59:59+01:00',
      email_status: 'planned',
      email_sent_at: null,
      email_failed_at: null,
      whatsapp_contacted_at: null,
    },
  ],
  whatsapp_manual_contacted_at: null,
  created_at: '2026-06-01T09:00:00+01:00',
};

describe('AdminBillingDocumentsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listAdminBillingDocuments).mockResolvedValue({
      id: 'admin-billing-documents',
      items: [DOCUMENT],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getAdminBillingDocument).mockResolvedValue(DOCUMENT);
    vi.mocked(openAdminBillingDocumentWhatsappContact).mockResolvedValue({
      id: 'doc-1-whatsapp',
      billing_document_id: 'doc-1',
      phone: '21620123456',
      message: 'Bonjour document MS-2026-000001',
      whatsapp_url: 'https://wa.me/21620123456?text=Bonjour',
    });
    vi.mocked(createAdminSubscriptionPayment).mockResolvedValue({
      id: 'payment-1',
      billing_document_id: 'doc-1',
      subscription_id: 'sub-1',
      merchant_id: 'merchant-1',
      merchant_email: 'ali@example.test',
      amount_tnd: '10.000',
      currency: 'TND',
      method: 'cash',
      reference: null,
      status: 'confirmed',
      paid_at: '2026-06-04T10:30:00+01:00',
      period_start: '2026-06-01T00:00:00+01:00',
      period_end: '2026-07-01T00:00:00+01:00',
      validated_by_admin_id: 'admin-1',
      validated_at: '2026-06-04T10:35:00+01:00',
    });
  });

  it('renders monthly billing documents with non-fiscal nature and TND amounts', async () => {
    render(<AdminBillingDocumentsPage />);

    expect(await screen.findByText('MS-2026-000001')).toBeInTheDocument();
    expect(screen.getAllByText('Document mensuel interne non fiscal')[0]).toBeInTheDocument();
    expect(screen.getByText('Émis')).toBeInTheDocument();
    expect(screen.getByText('10,000 TND')).toBeInTheDocument();
    expect(screen.getByText('08/06/2026')).toBeInTheDocument();
    expect(screen.getByText('ali@example.test')).toBeInTheDocument();
  });

  it('opens a read-only admin billing document detail', async () => {
    render(<AdminBillingDocumentsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir le détail document' }));

    await waitFor(() => expect(getAdminBillingDocument).toHaveBeenCalledWith('doc-1'));
    const detail = await screen.findByRole('dialog', { name: 'Détail document mensuel' });
    expect(within(detail).getByText('MS-2026-000001')).toBeInTheDocument();
    expect(within(detail).getByText('Reste à payer')).toBeInTheDocument();
    expect(within(detail).getAllByText('10,000 TND')).toHaveLength(2);
    expect(within(detail).getByText('Relances paiement')).toBeInTheDocument();
    expect(within(detail).getByText('J-7')).toBeInTheDocument();
    expect(within(detail).getByText('Email envoyé')).toBeInTheDocument();
    expect(within(detail).getByText('J-3')).toBeInTheDocument();
    expect(within(detail).getByText('Email planifié')).toBeInTheDocument();
  });

  it('opens WhatsApp with a contextual message from document detail', async () => {
    const popup = {
      opener: {},
      location: { href: '' },
      close: vi.fn(),
    } as unknown as Window;
    const openSpy = vi.spyOn(window, 'open').mockImplementation(() => popup);

    render(<AdminBillingDocumentsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir le détail document' }));
    const detail = await screen.findByRole('dialog', { name: 'Détail document mensuel' });
    fireEvent.click(within(detail).getByRole('button', { name: 'Contacter sur WhatsApp' }));

    expect(openSpy).toHaveBeenCalledWith('', '_blank');
    await waitFor(() => expect(openAdminBillingDocumentWhatsappContact).toHaveBeenCalledWith('doc-1'));
    expect(popup.opener).toBeNull();
    expect(popup.location.href).toBe('https://wa.me/21620123456?text=Bonjour');
    expect(getAdminBillingDocument).toHaveBeenLastCalledWith('doc-1');

    openSpy.mockRestore();
  });

  it('records a manual cash payment from billing document detail', async () => {
    const paidDocument = { ...DOCUMENT, status: 'paid' as const, amount_paid_tnd: '10.000', amount_due_tnd: '0.000' };
    vi.mocked(getAdminBillingDocument)
      .mockResolvedValueOnce(DOCUMENT)
      .mockResolvedValueOnce(paidDocument);

    render(<AdminBillingDocumentsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir le détail document' }));
    const detail = await screen.findByRole('dialog', { name: 'Détail document mensuel' });
    fireEvent.change(within(detail).getByLabelText('Moyen'), { target: { value: 'cash' } });
    fireEvent.click(within(detail).getByRole('button', { name: 'Enregistrer paiement' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Confirmer le paiement manuel' }));

    await waitFor(() => expect(createAdminSubscriptionPayment).toHaveBeenCalledWith({
      billing_document_id: 'doc-1',
      amount_tnd: '10.000',
      method: 'cash',
      paid_at: expect.stringMatching(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/),
      reference: '',
    }));
    expect(getAdminBillingDocument).toHaveBeenLastCalledWith('doc-1');
  });

  it('shows an explicit empty state', async () => {
    vi.mocked(listAdminBillingDocuments).mockResolvedValue({
      id: 'admin-billing-documents',
      items: [],
      page: 1,
      limit: 20,
      total: 0,
    });

    render(<AdminBillingDocumentsPage />);

    expect(await screen.findByText('Aucun document mensuel à afficher.')).toBeInTheDocument();
  });
});
