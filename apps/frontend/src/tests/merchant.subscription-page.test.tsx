import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantSubscriptionPage from '@/app/merchant/parametres/abonnement/page';
import MerchantSettingsPage from '@/app/merchant/parametres/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';
import {
  getMerchantBillingDocument,
  listMerchantBillingDocuments,
} from '@/lib/services/billing-documents.service';
import { listMerchantSubscriptionPayments } from '@/lib/services/subscription-payments.service';
import { getMerchantSubscription } from '@/lib/services/subscriptions.service';
import type { BillingDocument } from '@/lib/types/billing-documents.types';
import type { SubscriptionPayment } from '@/lib/types/subscription-payments.types';
import type { MerchantSubscription } from '@/lib/types/subscriptions.types';

vi.mock('@/lib/services/subscriptions.service', () => ({
  getMerchantSubscription: vi.fn(),
}));

vi.mock('@/lib/services/billing-documents.service', () => ({
  listMerchantBillingDocuments: vi.fn(),
  getMerchantBillingDocument: vi.fn(),
}));

vi.mock('@/lib/services/subscription-payments.service', () => ({
  listMerchantSubscriptionPayments: vi.fn(),
}));

const SUBSCRIPTION: MerchantSubscription = {
  id: 'sub-merchant',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  lifecycle: 'active',
  pricing_phase: 'standard',
  monthly_price_tnd: '50.000',
  currency: 'TND',
  started_at: '2026-01-01T00:00:00+01:00',
  current_period_started_at: '2026-06-01T00:00:00+01:00',
  current_period_ends_at: '2026-07-01T00:00:00+01:00',
  next_phase_change_at: null,
  created_at: '2026-01-01T00:00:00+01:00',
};

const DOCUMENT: BillingDocument = {
  id: 'doc-merchant',
  subscription_id: 'sub-merchant',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  document_number: 'MS-2026-000001',
  document_type: 'monthly_statement',
  document_nature_label: 'Document mensuel interne non fiscal',
  status: 'issued',
  pricing_phase: 'standard',
  currency: 'TND',
  billing_period_start: '2026-06-01T00:00:00+01:00',
  billing_period_end: '2026-07-01T00:00:00+01:00',
  issued_at: '2026-06-01T09:00:00+01:00',
  due_at: '2026-06-08T23:59:59+01:00',
  paid_at: null,
  cancelled_at: null,
  cancellation_reason: null,
  amount_tnd: '50.000',
  amount_paid_tnd: '0.000',
  amount_due_tnd: '50.000',
  reminder_schedule: [
    {
      stage: 'j_plus_3',
      label: 'J+3',
      scheduled_at: '2026-06-11T23:59:59+01:00',
      email_status: 'sent',
      email_sent_at: '2026-06-11T09:00:00+01:00',
      email_failed_at: null,
      whatsapp_contacted_at: null,
    },
  ],
  created_at: '2026-06-01T09:00:00+01:00',
};

const PAYMENT: SubscriptionPayment = {
  id: 'payment-merchant',
  billing_document_id: 'doc-merchant',
  subscription_id: 'sub-merchant',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  amount_tnd: '50.000',
  currency: 'TND',
  method: 'cash',
  reference: 'Caisse',
  status: 'confirmed',
  paid_at: '2026-06-04T10:30:00+01:00',
  period_start: '2026-06-01T00:00:00+01:00',
  period_end: '2026-07-01T00:00:00+01:00',
  validated_by_admin_id: 'admin-1',
  validated_at: '2026-06-04T10:35:00+01:00',
};

function renderWithLocale(children: React.ReactNode) {
  return render(<MerchantLocaleProvider>{children}</MerchantLocaleProvider>);
}

describe('MerchantSubscriptionPage', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
    vi.mocked(getMerchantSubscription).mockResolvedValue(SUBSCRIPTION);
    vi.mocked(listMerchantBillingDocuments).mockResolvedValue({
      id: 'merchant-billing-documents',
      items: [DOCUMENT],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getMerchantBillingDocument).mockResolvedValue(DOCUMENT);
    vi.mocked(listMerchantSubscriptionPayments).mockResolvedValue({
      id: 'merchant-subscription-payments',
      items: [PAYMENT],
      page: 1,
      limit: 20,
      total: 1,
    });
  });

  it('renders the current merchant subscription in read-only mode', async () => {
    renderWithLocale(<MerchantSubscriptionPage />);

    expect(await screen.findByText('Abonnement marchand')).toBeInTheDocument();
    expect(screen.getByText('Actif')).toBeInTheDocument();
    expect(screen.getByText('Standard')).toBeInTheDocument();
    expect(screen.getAllByText('50,000 TND')).toHaveLength(3);
    expect(screen.getByText('01/07/2026')).toBeInTheDocument();
    expect(await screen.findByText('MS-2026-000001')).toBeInTheDocument();
    expect(screen.getByText('Document mensuel interne non fiscal')).toBeInTheDocument();
    expect(await screen.findByText('Historique paiements')).toBeInTheDocument();
    expect(screen.getByText('Espèces')).toBeInTheDocument();
    expect(screen.getByText('Caisse')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /payer/i })).not.toBeInTheDocument();
  });

  it('explains overdue payment consequences without online payment action', async () => {
    vi.mocked(getMerchantSubscription).mockResolvedValue({
      ...SUBSCRIPTION,
      lifecycle: 'grace_period',
    });
    vi.mocked(listMerchantBillingDocuments).mockResolvedValue({
      id: 'merchant-billing-documents',
      items: [{ ...DOCUMENT, status: 'overdue' }],
      page: 1,
      limit: 20,
      total: 1,
    });

    renderWithLocale(<MerchantSubscriptionPage />);

    expect(await screen.findByText('Paiement abonnement attendu')).toBeInTheDocument();
    expect(screen.getByText(/Contactez l’équipe Kadhia après règlement manuel/i)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /payer/i })).not.toBeInTheDocument();
  });

  it('shows the subscription shortcut from merchant settings', () => {
    renderWithLocale(<MerchantSettingsPage />);

    expect(screen.getByRole('link', { name: /Abonnement marchand/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/abonnement',
    );
  });
});
