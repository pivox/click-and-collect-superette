'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import {
  getAdminBillingDocument,
  listAdminBillingDocuments,
  openAdminBillingDocumentWhatsappContact,
} from '@/lib/services/billing-documents.service';
import { createAdminSubscriptionPayment } from '@/lib/services/subscription-payments.service';
import type {
  BillingDocument,
  BillingDocumentStatus,
} from '@/lib/types/billing-documents.types';
import type { SubscriptionPaymentMethod } from '@/lib/types/subscription-payments.types';

const PAGE_SIZE = 20;

const STATUS_LABELS: Record<BillingDocumentStatus, string> = {
  draft: 'Brouillon',
  issued: 'Émis',
  paid: 'Payé',
  overdue: 'En retard',
  cancelled: 'Annulé',
};

const EMAIL_STATUS_LABELS: Record<string, string> = {
  planned: 'Email planifié',
  sent: 'Email envoyé',
  failed: 'Email échoué',
  not_configured: 'Email non configuré',
  not_applicable: 'Email non applicable',
};

function formatDate(value: string | null): string {
  if (!value) return 'Non planifié';

  const calendarDate = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  if (calendarDate) {
    const [, year, month, day] = calendarDate;

    return `${day}/${month}/${year}`;
  }

  return new Date(value).toLocaleDateString('fr-TN');
}

function formatMoney(value: string, currency: string): string {
  return `${Number(value).toLocaleString('fr-TN', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  })} ${currency}`;
}

function statusClass(status: BillingDocumentStatus): string {
  if (status === 'paid') {
    return 'bg-green-100 text-green-700';
  }
  if (status === 'overdue' || status === 'cancelled') {
    return 'bg-status-cancel-bg text-status-cancel';
  }
  if (status === 'draft') {
    return 'bg-soft text-muted';
  }

  return 'bg-[var(--status-wait-bg)] text-[var(--status-wait)]';
}

function formatDateTimeWithOffset(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0');
  const offsetMinutes = -date.getTimezoneOffset();
  const sign = offsetMinutes >= 0 ? '+' : '-';
  const absoluteOffset = Math.abs(offsetMinutes);

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
    + `T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
    + `${sign}${pad(Math.floor(absoluteOffset / 60))}:${pad(absoluteOffset % 60)}`;
}

export default function AdminBillingDocumentsPage() {
  const [items, setItems] = useState<BillingDocument[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [detail, setDetail] = useState<BillingDocument | null>(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<SubscriptionPaymentMethod>('cash');
  const [paymentPaidAt, setPaymentPaidAt] = useState('');
  const [paymentReference, setPaymentReference] = useState('');
  const [isPaymentConfirmOpen, setIsPaymentConfirmOpen] = useState(false);
  const [isPaymentSaving, setIsPaymentSaving] = useState(false);
  const [paymentError, setPaymentError] = useState<string | null>(null);
  const [isWhatsappOpening, setIsWhatsappOpening] = useState(false);
  const [whatsappError, setWhatsappError] = useState<string | null>(null);
  const detailRequestSeq = useRef(0);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listAdminBillingDocuments({ page, limit: PAGE_SIZE });
      setItems(data.items);
      setTotal(data.total);
    } catch (err) {
      console.error('[admin-billing-documents] list failed', err);
      setError('Impossible de charger les documents mensuels.');
    } finally {
      setIsLoading(false);
    }
  }, [page]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDetail = async (documentId: string) => {
    const requestSeq = detailRequestSeq.current + 1;
    detailRequestSeq.current = requestSeq;
    setIsDetailLoading(true);
    setError(null);
    try {
      const data = await getAdminBillingDocument(documentId);
      if (detailRequestSeq.current !== requestSeq) return;
      setDetail(data);
      setPaymentMethod('cash');
      setPaymentPaidAt('');
      setPaymentReference('');
      setPaymentError(null);
      setWhatsappError(null);
    } catch (err) {
      if (detailRequestSeq.current !== requestSeq) return;
      console.error('[admin-billing-documents] detail failed', err);
      setError('Impossible de charger le détail du document mensuel.');
    } finally {
      if (detailRequestSeq.current === requestSeq) {
        setIsDetailLoading(false);
      }
    }
  };

  const closeDetail = () => {
    detailRequestSeq.current += 1;
    setDetail(null);
    setIsDetailLoading(false);
    setIsPaymentConfirmOpen(false);
    setPaymentError(null);
    setWhatsappError(null);
  };

  const paidAtToAtom = (value: string): string => {
    if (!value) {
      return formatDateTimeWithOffset(new Date());
    }

    return `${value}:00+01:00`;
  };

  const confirmPayment = async () => {
    if (!detail) return;

    setIsPaymentSaving(true);
    setPaymentError(null);
    try {
      await createAdminSubscriptionPayment({
        billing_document_id: detail.id,
        amount_tnd: detail.amount_due_tnd,
        method: paymentMethod,
        paid_at: paidAtToAtom(paymentPaidAt),
        reference: paymentReference,
      });
      const refreshed = await getAdminBillingDocument(detail.id);
      setDetail(refreshed);
      await load();
      setIsPaymentConfirmOpen(false);
    } catch (err: unknown) {
      const status = typeof err === 'object' && err && 'response' in err
        ? (err as { response?: { status?: number } }).response?.status
        : undefined;
      setPaymentError(status === 409
        ? 'Ce document est déjà payé.'
        : 'Impossible d’enregistrer le paiement manuel.');
    } finally {
      setIsPaymentSaving(false);
    }
  };

  const contactOnWhatsapp = async () => {
    if (!detail) return;

    setIsWhatsappOpening(true);
    setWhatsappError(null);
    try {
      const contact = await openAdminBillingDocumentWhatsappContact(detail.id);
      window.open(contact.whatsapp_url, '_blank', 'noopener,noreferrer');
      const refreshed = await getAdminBillingDocument(detail.id);
      setDetail(refreshed);
    } catch (err) {
      console.error('[admin-billing-documents] whatsapp contact failed', err);
      setWhatsappError('Impossible de préparer le contact WhatsApp.');
    } finally {
      setIsWhatsappOpening(false);
    }
  };

  const columns: Column<BillingDocument>[] = [
    {
      key: 'document_number',
      label: 'Document',
      render: (row) => (
        <div>
          <div className="font-medium">{row.document_number}</div>
          <div className="text-xs text-muted">{row.document_nature_label}</div>
        </div>
      ),
    },
    {
      key: 'merchant_email',
      label: 'Marchand',
      render: (row) => (
        <div>
          <div className="font-medium">{row.merchant_email}</div>
          <div className="text-xs text-muted">{row.merchant_id}</div>
        </div>
      ),
    },
    {
      key: 'status',
      label: 'Statut',
      render: (row) => (
        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusClass(row.status)}`}>
          {STATUS_LABELS[row.status]}
        </span>
      ),
    },
    {
      key: 'amount_tnd',
      label: 'Montant',
      render: (row) => <span className="tabular-nums">{formatMoney(row.amount_tnd, row.currency)}</span>,
    },
    {
      key: 'due_at',
      label: 'Échéance',
      render: (row) => formatDate(row.due_at),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <button
          type="button"
          onClick={() => void openDetail(row.id)}
          disabled={isDetailLoading}
          aria-label="Voir le détail document"
          className="text-xs font-semibold text-primary hover:underline disabled:text-muted"
        >
          {isDetailLoading ? 'Chargement…' : 'Voir détail'}
        </button>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Facturation</h1>
          <p className="mt-1 text-sm text-muted">
            Documents mensuels internes non fiscaux liés aux abonnements marchands.
          </p>
        </div>
        <Button variant="ghost" size="md" onClick={() => void load()}>
          {error ? 'Réessayer' : 'Actualiser'}
        </Button>
      </div>

      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <AdminTable
        columns={columns}
        data={items}
        isLoading={isLoading}
        emptyMessage="Aucun document mensuel à afficher."
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
      />

      {detail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <section
            role="dialog"
            aria-label="Détail document mensuel"
            className="w-full max-w-xl rounded-md bg-card p-5 shadow-xl"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black text-ink">{detail.document_number}</h2>
                <p className="mt-1 text-sm text-muted">{detail.document_nature_label}</p>
              </div>
              <button type="button" className="text-sm font-bold text-muted" onClick={closeDetail}>
                Fermer
              </button>
            </div>

            <dl className="mt-5 grid gap-3 sm:grid-cols-2">
              <Detail label="Statut" value={STATUS_LABELS[detail.status]} />
              <Detail label="Marchand" value={detail.merchant_email} />
              <Detail label="Période" value={`${formatDate(detail.billing_period_start)} - ${formatDate(detail.billing_period_end)}`} />
              <Detail label="Échéance" value={formatDate(detail.due_at)} />
              <Detail label="Montant" value={formatMoney(detail.amount_tnd, detail.currency)} />
              <Detail label="Reste à payer" value={formatMoney(detail.amount_due_tnd, detail.currency)} />
            </dl>

            <section className="mt-5 space-y-3 rounded-md border border-line bg-soft p-3">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 className="text-sm font-black text-ink">Relances paiement</h3>
                <Button
                  type="button"
                  variant="ghost"
                  size="md"
                  onClick={() => void contactOnWhatsapp()}
                  disabled={isWhatsappOpening}
                >
                  Contacter sur WhatsApp
                </Button>
              </div>
              {whatsappError && (
                <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                  {whatsappError}
                </div>
              )}
              <div className="grid gap-2 sm:grid-cols-2">
                {detail.reminder_schedule.map((reminder) => (
                  <div key={reminder.stage} className="rounded-md border border-line bg-card px-3 py-2">
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-sm font-black text-ink">{reminder.label}</span>
                      <span className="text-xs font-semibold text-muted">{formatDate(reminder.scheduled_at)}</span>
                    </div>
                    <div className="mt-1 text-xs font-semibold text-muted">
                      {EMAIL_STATUS_LABELS[reminder.email_status] ?? reminder.email_status}
                    </div>
                    {reminder.whatsapp_contacted_at && (
                      <div className="mt-1 text-xs font-semibold text-primary">
                        WhatsApp préparé le {formatDate(reminder.whatsapp_contacted_at)}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </section>

            {detail.status !== 'paid' && detail.status !== 'cancelled' && (
              <form
                className="mt-5 space-y-3 rounded-md border border-line bg-soft p-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  setIsPaymentConfirmOpen(true);
                }}
              >
                <h3 className="text-sm font-black text-ink">Paiement manuel</h3>
                <div className="grid gap-3 sm:grid-cols-2">
                  <label className="text-xs font-semibold text-muted">
                    Moyen
                    <select
                      className="mt-1 w-full rounded-md border border-line bg-card px-3 py-2 text-sm text-ink"
                      value={paymentMethod}
                      onChange={(event) => setPaymentMethod(event.target.value as SubscriptionPaymentMethod)}
                    >
                      <option value="cash">Espèces</option>
                      <option value="bank_transfer">Virement</option>
                    </select>
                  </label>
                  <label className="text-xs font-semibold text-muted">
                    Date de paiement
                    <input
                      type="datetime-local"
                      className="mt-1 w-full rounded-md border border-line bg-card px-3 py-2 text-sm text-ink"
                      value={paymentPaidAt}
                      onChange={(event) => setPaymentPaidAt(event.target.value)}
                    />
                  </label>
                </div>
                <label className="block text-xs font-semibold text-muted">
                  Référence
                  <input
                    type="text"
                    className="mt-1 w-full rounded-md border border-line bg-card px-3 py-2 text-sm text-ink"
                    value={paymentReference}
                    onChange={(event) => setPaymentReference(event.target.value)}
                  />
                </label>
                {paymentError && (
                  <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                    {paymentError}
                  </div>
                )}
                <Button type="submit" variant="primary" size="md" disabled={isPaymentSaving}>
                  Enregistrer paiement
                </Button>
              </form>
            )}
          </section>
        </div>
      )}

      {isPaymentConfirmOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
          <section className="w-full max-w-md rounded-md bg-card p-5 shadow-xl">
            <h2 className="text-lg font-black text-ink">Confirmer le paiement manuel</h2>
            <p className="mt-2 text-sm text-muted">
              Cette action marque le document payé et peut réactiver l’abonnement marchand.
            </p>
            <div className="mt-5 flex justify-end gap-2">
              <Button variant="ghost" size="md" onClick={() => setIsPaymentConfirmOpen(false)}>
                Annuler
              </Button>
              <Button
                variant="primary"
                size="md"
                onClick={() => void confirmPayment()}
                disabled={isPaymentSaving}
              >
                Confirmer le paiement manuel
              </Button>
            </div>
          </section>
        </div>
      )}
    </div>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-line bg-soft px-3 py-2">
      <dt className="text-xs font-semibold uppercase text-muted">{label}</dt>
      <dd className="mt-1 text-sm font-bold text-ink">{value}</dd>
    </div>
  );
}
