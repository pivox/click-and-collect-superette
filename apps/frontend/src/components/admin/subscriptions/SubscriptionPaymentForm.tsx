'use client';

import { useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { createAdminSubscriptionPayment } from '@/lib/services/subscription-payments.service';
import type { BillingDocument } from '@/lib/types/billing-documents.types';
import type { CreateSubscriptionPaymentPayload, SubscriptionPaymentMethod } from '@/lib/types/subscription-payments.types';

function millimes(value: string): number {
  if (!/^\d{1,10}(\.\d{1,3})?$/.test(value)) return NaN;
  const [whole, fraction = ''] = value.split('.');
  return Number(whole) * 1000 + Number(fraction.padEnd(3, '0'));
}

function money(value: number): string {
  return `${(value / 1000).toLocaleString('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} TND`;
}

function requestKey(): string {
  // getRandomValues also works on the local HTTP interface outside secure contexts.
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

// Tunisia stays on UTC+01:00 all year, matching the offset sent to the API.
function tunisDateTimeLocal(date: Date = new Date()): string {
  return new Date(date.getTime() + 3_600_000).toISOString().slice(0, 16);
}

function readPending(storageKey: string): CreateSubscriptionPaymentPayload | null {
  try { return JSON.parse(sessionStorage.getItem(storageKey) ?? 'null'); } catch { return null; }
}

export function SubscriptionPaymentForm({ document, onSaved, onBusyChange }: {
  document: BillingDocument;
  onSaved: () => void | Promise<void>;
  onBusyChange?: (busy: boolean) => void;
}) {
  const storageKey = `kadhia:pending-subscription-payment:${document.id}`;
  const [pending, setPending] = useState<CreateSubscriptionPaymentPayload | null>(() => readPending(storageKey));
  const [amount, setAmount] = useState(pending?.amount_tnd ?? document.amount_due_tnd);
  const [method, setMethod] = useState<SubscriptionPaymentMethod>(pending?.method ?? 'cash');
  const [paidAt, setPaidAt] = useState(() => {
    const pendingDate = pending ? new Date(pending.paid_at) : null;
    return pendingDate && Number.isFinite(pendingDate.getTime()) ? tunisDateTimeLocal(pendingDate) : tunisDateTimeLocal();
  });
  const [reference, setReference] = useState(pending?.reference ?? '');
  const [confirming, setConfirming] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const lock = useRef(false);
  const balance = millimes(document.amount_due_tnd);
  const amountValue = millimes(amount);

  const prepare = () => {
    setError(null);
    if (!Number.isFinite(amountValue) || amountValue <= 0) { setError('Saisis un montant positif avec au maximum trois décimales.'); return; }
    if (amountValue > balance) { setError('Le montant dépasse le solde restant à payer.'); return; }
    const date = paidAt ? new Date(`${paidAt}:00+01:00`) : new Date();
    if (!Number.isFinite(date.getTime()) || date > new Date()) { setError('La date du règlement doit être valide et ne peut pas être future.'); return; }
    setConfirming(true);
  };

  const record = async () => {
    if (lock.current || saved) return;
    lock.current = true;
    setSaving(true);
    onBusyChange?.(true);
    setError(null);
    const payload = pending ?? {
      billing_document_id: document.id, amount_tnd: (amountValue / 1000).toFixed(3), method,
      paid_at: paidAt ? `${paidAt}:00+01:00` : new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00'),
      reference: reference.trim(), idempotency_key: requestKey(),
    };
    setPending(payload);
    try { sessionStorage.setItem(storageKey, JSON.stringify(payload)); } catch { /* The in-memory key still protects retries. */ }
    try {
      await createAdminSubscriptionPayment(payload);
      try { sessionStorage.removeItem(storageKey); } catch { /* A persisted replay remains safe. */ }
      setSaved(true);
      setPending(null);
      setConfirming(false);
      try { await onSaved(); } catch { setError('Le règlement est enregistré. Actualise la page pour consulter le solde à jour.'); }
    } catch (err) {
      const response = (err as { response?: { status?: number; data?: { detail?: string } } })?.response;
      const status = response?.status;
      const keyConflict = status === 409 && response?.data?.detail === 'SUBSCRIPTION_PAYMENT_IDEMPOTENCY_CONFLICT';
      if (status && status >= 400 && status < 500 && ![401, 403, 408, 429].includes(status) && !keyConflict) {
        try { sessionStorage.removeItem(storageKey); } catch { /* Keep the form usable if storage is unavailable. */ }
        setPending(null);
        setConfirming(false);
        setError(status === 409
          ? 'Ce règlement ne peut pas être confirmé. Actualise le document pour vérifier son état et son solde.'
          : 'Règlement refusé. Vérifie le montant, la date et le solde du document.');
      } else {
        setError(status === 401 || status === 403 || keyConflict
          ? 'Reconnecte-toi avec le compte admin ayant saisi ce règlement, puis réessaie. La demande est conservée pour éviter un doublon.'
          : 'La confirmation n’a pas été reçue. Réessaie le même règlement pour vérifier son enregistrement sans doublon.');
      }
    } finally { lock.current = false; setSaving(false); onBusyChange?.(false); }
  };

  if (saved) return <div className="mt-5 space-y-2"><p role="status" className="font-semibold text-primary">Règlement enregistré.</p>{error && <p role="alert">{error}</p>}</div>;
  if (!pending && (!['issued', 'overdue'].includes(document.status) || balance <= 0)) return null;

  return <form aria-label="Paiement manuel" className="mt-5 space-y-4 rounded-xl border border-line bg-soft p-4" onSubmit={(event) => { event.preventDefault(); prepare(); }}>
    <h3 className="font-bold">Paiement manuel</h3>
    <fieldset disabled={saving || confirming || pending !== null} className="grid gap-3 sm:grid-cols-2 disabled:opacity-70">
      <label className="text-sm font-semibold">Montant du règlement (TND)
        <input type="number" min="0.001" max={document.amount_due_tnd} step="0.001" required value={amount} onChange={(event) => setAmount(event.target.value)} className="mt-1 min-h-[44px] w-full rounded-md border border-line bg-card px-3" />
      </label>
      <label className="text-sm font-semibold">Moyen
        <select value={method} onChange={(event) => setMethod(event.target.value as SubscriptionPaymentMethod)} className="mt-1 min-h-[44px] w-full rounded-md border border-line bg-card px-3"><option value="cash">Espèces</option><option value="bank_transfer">Virement</option></select>
      </label>
      <label className="text-sm font-semibold">Date de paiement
        <input type="datetime-local" value={paidAt} onChange={(event) => setPaidAt(event.target.value)} className="mt-1 min-h-[44px] w-full min-w-0 rounded-md border border-line bg-card px-3" />
        <span className="text-xs font-normal text-muted">Heure de Tunisie. Pré-remplie avec la date et l’heure actuelles.</span>
      </label>
      <label className="text-sm font-semibold">Référence
        <input maxLength={120} value={reference} onChange={(event) => setReference(event.target.value)} className="mt-1 min-h-[44px] w-full rounded-md border border-line bg-card px-3" />
      </label>
    </fieldset>
    {error && <p role="alert" className="text-sm text-status-cancel">{error}</p>}
    {pending ? <div className="space-y-3">
      <p className="text-sm">Vérification du versement de {money(millimes(pending.amount_tnd))}. Les informations sont conservées pour éviter un doublon.</p>
      <Button type="button" size="md" disabled={saving} onClick={() => void record()}>{saving ? 'Enregistrement…' : 'Réessayer le même règlement'}</Button>
    </div> : confirming ? <div className="space-y-3">
      <p className="text-sm">Confirmer {money(amountValue)} par {method === 'cash' ? 'espèces' : 'virement'}. Solde après règlement : <strong>{money(balance - amountValue)}</strong>.</p>
      <p className="text-xs text-muted">Un versement partiel ne solde pas le document. La réactivation dépend des échéances impayées.</p>
      <div className="flex flex-wrap gap-2"><Button type="button" variant="ghost" size="md" onClick={() => setConfirming(false)}>Modifier</Button><Button type="button" size="md" onClick={() => void record()}>Confirmer le paiement manuel</Button></div>
    </div> : <Button type="submit" size="md">Enregistrer paiement</Button>}
  </form>;
}
