import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminBillingDocumentsPage from '@/app/admin/facturation/page';
import {
  getAdminBillingDocument,
  listAdminBillingDocuments,
} from '@/lib/services/billing-documents.service';
import type { BillingDocument } from '@/lib/types/billing-documents.types';

vi.mock('@/lib/services/billing-documents.service', () => ({
  listAdminBillingDocuments: vi.fn(),
  getAdminBillingDocument: vi.fn(),
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
