'use client';

import Link from 'next/link';
import { useRef, useState } from 'react';
import { isAxiosError } from 'axios';
import { Button } from '@/components/ui/Button';
import { issueSubscriptionBillingDocument } from '@/lib/services/subscriptions.service';

export function IssueSubscriptionDocument({ subscriptionId, amountTnd, onBusyChange }: { subscriptionId: string; amountTnd: string; onBusyChange?: (busy: boolean) => void }) {
  const [documentNumber, setDocumentNumber] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const lock = useRef(false);
  if (Number(amountTnd) === 0) return <p className="mt-4 text-sm text-muted">Aucun paiement à demander pour cette période gratuite. Les éventuels impayés antérieurs restent consultables dans la facturation.</p>;

  const issue = async () => {
    if (lock.current) return;
    lock.current = true;
    setBusy(true);
    onBusyChange?.(true);
    setError(null);
    try {
      const document = await issueSubscriptionBillingDocument(subscriptionId);
      setDocumentNumber(document.document_number);
    } catch (err) {
      setError(isAxiosError(err) && err.response?.status === 422
        ? 'Cette période est gratuite. Aucun document de paiement à émettre.'
        : 'Impossible d’émettre le document. Actualise l’abonnement puis réessaie.');
    } finally { lock.current = false; setBusy(false); onBusyChange?.(false); }
  };

  return <section className="mt-5 space-y-3 border-t border-line pt-4">
    <h3 className="font-bold">Paiement de la période en cours</h3>
    <p className="text-sm text-muted">Émets le document interne non fiscal au montant affiché. S’il existe déjà pour cette période, il sera réutilisé.</p>
    {error && <p role="alert" className="text-sm text-status-cancel">{error}</p>}
    {documentNumber ? <div role="status" className="space-y-2 text-sm">
      <p>Document disponible : <strong>{documentNumber}</strong>.</p>
      <Link href="/admin/facturation" className="inline-block min-h-[44px] py-2 font-semibold text-primary underline">Enregistrer le paiement manuel</Link>
    </div> : <Button type="button" variant="ghost" size="md" disabled={busy} onClick={() => void issue()}>
      {busy ? 'Émission en cours…' : 'Émettre le document de période'}
    </Button>}
  </section>;
}
