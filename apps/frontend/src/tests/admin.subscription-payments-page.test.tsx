import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SubscriptionPaymentsManager } from '@/components/admin/subscriptions/SubscriptionPaymentsManager';
import { getAdminBillingDocument, listAdminBillingDocuments } from '@/lib/services/billing-documents.service';
import { listAdminSubscriptionPayments } from '@/lib/services/subscription-payments.service';
import { PAYMENT_DOCUMENT } from './fixtures/subscription-payment';

vi.mock('@/lib/services/billing-documents.service', () => ({ listAdminBillingDocuments: vi.fn(), getAdminBillingDocument: vi.fn() }));
vi.mock('@/lib/services/subscription-payments.service', () => ({ listAdminSubscriptionPayments: vi.fn(), createAdminSubscriptionPayment: vi.fn() }));

describe('SubscriptionPaymentsManager', () => {
  beforeEach(() => {
    vi.resetAllMocks(); sessionStorage.clear();
    vi.mocked(listAdminBillingDocuments).mockResolvedValue({ id: 'docs', items: [{ ...PAYMENT_DOCUMENT, amount_paid_tnd: '20.000', amount_due_tnd: '30.000' }], page: 1, limit: 20, total: 1 });
    vi.mocked(getAdminBillingDocument).mockResolvedValue(PAYMENT_DOCUMENT);
    vi.mocked(listAdminSubscriptionPayments).mockResolvedValue({ id: 'payments', items: [], page: 1, limit: 20, total: 0 });
  });

  it('shows paid and outstanding amounts with a subscription filter', async () => {
    render(<SubscriptionPaymentsManager subscriptionId="sub-1" />);
    expect(await screen.findByText('Partiellement réglé')).toBeInTheDocument();
    expect(screen.getByText('20,000 TND')).toBeInTheDocument();
    expect(screen.getByText('30,000 TND')).toBeInTheDocument();
    expect(listAdminBillingDocuments).toHaveBeenCalledWith(expect.objectContaining({ subscription_id: 'sub-1', outstanding: true }));
    fireEvent.click(screen.getByRole('button', { name: 'Historique des règlements' }));
    expect(await screen.findByText('Aucun règlement enregistré.')).toBeInTheDocument();
    expect(listAdminSubscriptionPayments).toHaveBeenCalledWith(expect.objectContaining({ subscription_id: 'sub-1' }));
  });

  it('searches on the server and opens the selected document', async () => {
    render(<SubscriptionPaymentsManager />);
    await screen.findByText('MS-2026-000001');
    fireEvent.change(screen.getByLabelText('Rechercher'), { target: { value: 'ali@example.test' } });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));
    await waitFor(() => expect(listAdminBillingDocuments).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'ali@example.test', page: 1 })));
    fireEvent.click(screen.getByRole('button', { name: 'Régler MS-2026-000001' }));
    expect(await screen.findByRole('dialog', { name: 'Règlement du document' })).toBeInTheDocument();
    expect(getAdminBillingDocument).toHaveBeenCalledWith('doc-1');
    expect(screen.getByLabelText('Montant du règlement (TND)')).toHaveValue(50);
  });

  it('loads a document linked from an issued subscription period', async () => {
    render(<SubscriptionPaymentsManager subscriptionId="sub-1" documentId="doc-1" />);
    expect(await screen.findByRole('dialog', { name: 'Règlement du document' })).toBeInTheDocument();
    expect(getAdminBillingDocument).toHaveBeenCalledWith('doc-1');
  });
});
