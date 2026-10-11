'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import { BillingDocumentPricingSummary } from '@/components/subscriptions/BillingDocumentPricingSummary';
import { getAdminBillingDocument, listAdminBillingDocuments } from '@/lib/services/billing-documents.service';
import { listAdminSubscriptionPayments } from '@/lib/services/subscription-payments.service';
import type { BillingDocument } from '@/lib/types/billing-documents.types';
import type { SubscriptionPayment } from '@/lib/types/subscription-payments.types';
import { SubscriptionDialog } from './SubscriptionDialog';
import { SubscriptionPaymentForm } from './SubscriptionPaymentForm';

const LIMIT = 20;
const money = (amount: string) => `${Number(amount).toLocaleString('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} TND`;
const date = (value: string | null) => value ? new Date(value).toLocaleDateString('fr-TN', { timeZone: 'Africa/Tunis' }) : '—';

export function documentPaymentLabel(document: BillingDocument): string {
  if (document.status === 'cancelled') return 'Annulé';
  if (document.status === 'draft') return 'Brouillon';
  if (document.status === 'paid') return 'Payé';
  if (Number(document.amount_paid_tnd) > 0) return document.status === 'overdue' ? 'Partiellement réglé · En retard' : 'Partiellement réglé';
  return document.status === 'overdue' ? 'En retard' : 'À régler';
}

export function SubscriptionPaymentsManager({ subscriptionId, documentId }: { subscriptionId?: string; documentId?: string }) {
  const [view, setView] = useState<'documents' | 'payments'>('documents');
  const [documents, setDocuments] = useState<BillingDocument[]>([]);
  const [payments, setPayments] = useState<SubscriptionPayment[]>([]);
  const [documentPage, setDocumentPage] = useState(1);
  const [paymentPage, setPaymentPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [onlyOutstanding, setOnlyOutstanding] = useState(true);
  const [historyDocumentId, setHistoryDocumentId] = useState(documentId);
  const [detail, setDetail] = useState<BillingDocument | null>(null);
  const [loading, setLoading] = useState(true);
  const [opening, setOpening] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const request = useRef(0);
  const detailRequest = useRef(0);

  const load = useCallback(async () => {
    const sequence = ++request.current;
    setLoading(true); setError(null);
    try {
      const filters = { limit: LIMIT, subscription_id: subscriptionId, q: query || undefined };
      if (view === 'documents') {
        const result = await listAdminBillingDocuments({ ...filters, page: documentPage, outstanding: onlyOutstanding ? true : undefined });
        if (sequence !== request.current) return;
        setDocuments(result.items); setTotal(result.total);
      } else {
        const result = await listAdminSubscriptionPayments({ ...filters, page: paymentPage, billing_document_id: historyDocumentId });
        if (sequence !== request.current) return;
        setPayments(result.items); setTotal(result.total);
      }
    } catch { if (sequence === request.current) { setDocuments([]); setPayments([]); setTotal(0); setError('Impossible de charger les règlements. Réessaie.'); } }
    finally { if (sequence === request.current) setLoading(false); }
  }, [subscriptionId, query, view, documentPage, paymentPage, onlyOutstanding, historyDocumentId]);

  useEffect(() => { void load(); return () => { request.current += 1; }; }, [load]);

  const open = useCallback(async (id: string) => {
    const sequence = ++detailRequest.current;
    setOpening(true); setError(null);
    try { const result = await getAdminBillingDocument(id); if (sequence === detailRequest.current) setDetail(result); }
    catch { if (sequence === detailRequest.current) setError('Impossible de charger ce document.'); }
    finally { if (sequence === detailRequest.current) setOpening(false); }
  }, []);

  useEffect(() => { if (documentId) void open(documentId); return () => { detailRequest.current += 1; }; }, [documentId, open]);

  const documentColumns: Column<BillingDocument>[] = [
    { key: 'document_number', label: 'Document / Marchand', render: (row) => <div><p className="font-semibold">{row.document_number}</p><p className="text-xs text-muted">{row.merchant_email}</p></div> },
    { key: 'period', label: 'Période / Échéance', render: (row) => <div><p>{date(row.billing_period_start)} – {date(row.billing_period_end)}</p><p className="text-xs text-muted">Échéance : {date(row.due_at)}</p></div> },
    { key: 'amount_tnd', label: 'Facturé', render: (row) => money(row.amount_tnd) },
    { key: 'amount_paid_tnd', label: 'Réglé', render: (row) => money(row.amount_paid_tnd) },
    { key: 'amount_due_tnd', label: 'Reste à payer', render: (row) => <strong>{money(row.amount_due_tnd)}</strong> },
    { key: 'status', label: 'Statut', render: documentPaymentLabel },
    { key: 'actions', label: 'Action', render: (row) => <Button size="md" variant="ghost" disabled={opening} aria-label={`Régler ${row.document_number}`} onClick={() => void open(row.id)}>Voir / Régler</Button> },
  ];
  const paymentColumns: Column<SubscriptionPayment>[] = [
    { key: 'paid_at', label: 'Date', render: (row) => date(row.paid_at) },
    { key: 'merchant_email', label: 'Marchand' },
    { key: 'document_number', label: 'Document', render: (row) => <button className="min-h-[44px] text-primary underline" disabled={opening} onClick={() => void open(row.billing_document_id)}>{row.document_number ?? 'Voir le document'}</button> },
    { key: 'amount_tnd', label: 'Montant réglé', render: (row) => money(row.amount_tnd) },
    { key: 'method', label: 'Moyen', render: (row) => row.method === 'cash' ? 'Espèces' : 'Virement' },
    { key: 'reference', label: 'Référence', render: (row) => row.reference || '—' },
    { key: 'period_start', label: 'Période couverte', render: (row) => `${date(row.period_start)} – ${date(row.period_end)}` },
  ];

  return <div className="space-y-5">
    <div className="flex flex-wrap items-start justify-between gap-3"><div><h1 className="text-h1 font-black">Règlements</h1><p className="mt-1 text-sm text-muted">Versements manuels des abonnements mensuels et annuels, après remises.</p></div><Button size="md" variant="ghost" disabled={loading} onClick={() => void load()}>Actualiser</Button></div>
    {subscriptionId && <div className="flex flex-wrap items-center gap-3 rounded-lg bg-soft p-3 text-sm"><span>Règlements de cet abonnement</span><Link href="/admin/reglements" className="min-h-[44px] py-3 text-primary underline">Tous les abonnements</Link></div>}
    {notice && <p role="status" className="text-sm font-semibold text-primary">{notice}</p>}
    {error && <p role="alert" className="text-sm text-status-cancel">{error}</p>}
    <div className="flex flex-wrap gap-2" aria-label="Vue des règlements"><Button size="md" variant={view === 'documents' ? 'primary' : 'ghost'} aria-pressed={view === 'documents'} onClick={() => setView('documents')}>Documents à régler</Button><Button size="md" variant={view === 'payments' ? 'primary' : 'ghost'} aria-pressed={view === 'payments'} onClick={() => setView('payments')}>Historique des règlements</Button></div>
    <form className="flex flex-wrap items-end gap-3" onSubmit={(event) => { event.preventDefault(); setQuery(search.trim()); setDocumentPage(1); setPaymentPage(1); }}>
      <label className="min-w-0 flex-1 text-sm font-semibold">Rechercher<input type="search" value={search} maxLength={120} onChange={(event) => setSearch(event.target.value)} placeholder="Email marchand, document ou référence" className="mt-1 min-h-[44px] w-full rounded-md border border-line bg-card px-3" /></label><Button type="submit" size="md">Rechercher</Button>
    </form>
    {view === 'documents' ? <><label className="flex min-h-[44px] items-center gap-2 text-sm"><input type="checkbox" checked={onlyOutstanding} onChange={(event) => { setOnlyOutstanding(event.target.checked); setDocumentPage(1); }} />Uniquement les documents avec un reste à payer</label><AdminTable columns={documentColumns} data={documents} isLoading={loading} emptyMessage="Aucun document à afficher. Les périodes gratuites ne nécessitent pas de règlement." pagination={{ page: documentPage, total, limit: LIMIT, onPageChange: setDocumentPage }} /></> : <>
      {historyDocumentId && <div className="flex flex-wrap items-center gap-3 text-sm"><span>Historique limité au document sélectionné</span><Button size="md" variant="ghost" onClick={() => { setHistoryDocumentId(undefined); setPaymentPage(1); }}>Tous les documents</Button></div>}
      <AdminTable columns={paymentColumns} data={payments} isLoading={loading} emptyMessage="Aucun règlement enregistré." pagination={{ page: paymentPage, total, limit: LIMIT, onPageChange: setPaymentPage }} />
    </>}
    {detail && <SubscriptionDialog title="Règlement du document" busy={busy} onClose={() => setDetail(null)}>
      <p className="font-bold">{detail.document_number}</p><p className="break-all text-sm text-muted">{detail.merchant_email}</p><p className="mt-2 text-sm">Période : {date(detail.billing_period_start)} – {date(detail.billing_period_end)}</p>
      <dl className="my-4 grid grid-cols-1 gap-3 rounded-lg bg-soft p-3 sm:grid-cols-3">{[['Facturé', detail.amount_tnd], ['Réglé', detail.amount_paid_tnd], ['Reste à payer', detail.amount_due_tnd]].map(([label, value]) => <div key={label}><dt className="text-xs text-muted">{label}</dt><dd className="font-bold">{money(value)}</dd></div>)}</dl>
      <p className="text-sm font-semibold">{documentPaymentLabel(detail)}</p><BillingDocumentPricingSummary document={detail} />
      <SubscriptionPaymentForm key={detail.id} document={detail} onBusyChange={setBusy} onSaved={async () => { setNotice('Règlement enregistré.'); setDetail(null); await load(); }} />
      <Button className="mt-4" size="md" variant="ghost" disabled={busy} onClick={() => { setHistoryDocumentId(detail.id); setPaymentPage(1); setView('payments'); setDetail(null); }}>Voir les règlements de ce document</Button>
    </SubscriptionDialog>}
  </div>;
}
