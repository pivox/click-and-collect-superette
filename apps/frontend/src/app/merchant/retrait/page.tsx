'use client';

import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { formatTime, formatTnd } from '@/lib/format';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  scanMerchantPickupSession,
} from '@/lib/services/merchant-pickup.service';
import type {
  MerchantPickupSessionActionResult,
  MerchantPickupSessionForceCompleteResult,
  MerchantPickupSessionScanResult,
} from '@/lib/types/merchant.types';

const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function apiErrorMessage(error: unknown): string {
  if (
    typeof error === 'object' &&
    error !== null &&
    'response' in error &&
    typeof (error as { response?: { data?: { detail?: unknown } } }).response?.data?.detail ===
      'string'
  ) {
    return (error as { response: { data: { detail: string } } }).response.data.detail;
  }

  return "L'action n'a pas pu être effectuée. Vérifie le QR code puis réessaie.";
}

function customerName(session: MerchantPickupSessionScanResult): string {
  return (
    [session.customer.first_name, session.customer.last_name].filter(Boolean).join(' ') ||
    'Client non renseigné'
  );
}

function actionStatusText(
  action: MerchantPickupSessionActionResult | MerchantPickupSessionForceCompleteResult | null,
): string {
  if (!action) return 'QR scanné. Retrait en attente de confirmation marchand.';
  if (action.is_completed) return 'Retrait finalisé.';
  if (action.merchant_confirmed_at) return 'Confirmation marchand enregistrée.';
  return 'Retrait en attente.';
}

export default function MerchantPickupPage() {
  const [token, setToken] = useState('');
  const [session, setSession] = useState<MerchantPickupSessionScanResult | null>(null);
  const [actionResult, setActionResult] = useState<
    MerchantPickupSessionActionResult | MerchantPickupSessionForceCompleteResult | null
  >(null);
  const [forceNote, setForceNote] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isScanning, setIsScanning] = useState(false);
  const [isMutating, setIsMutating] = useState(false);

  const trimmedToken = token.trim();
  const orderLabel = session?.order_number ?? session?.order_id ?? '';
  const canForceComplete =
    !!actionResult?.merchant_confirmed_at &&
    !actionResult.customer_confirmed_at &&
    !actionResult.is_completed;
  const totalTnd = useMemo(() => {
    if (!session) return '0.000';
    const total = session.lines.reduce(
      (sum, line) => sum + Number.parseFloat(line.unit_price_tnd) * line.quantity,
      0,
    );
    return total.toFixed(3);
  }, [session]);

  const scan = async () => {
    setError(null);
    setActionResult(null);

    if (!UUID_PATTERN.test(trimmedToken)) {
      setError('Le token QR doit être un UUID valide.');
      return;
    }

    setIsScanning(true);
    try {
      setSession(await scanMerchantPickupSession(trimmedToken));
      setForceNote('');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsScanning(false);
    }
  };

  const confirm = async () => {
    if (!session) return;
    setIsMutating(true);
    setError(null);
    try {
      setActionResult(await confirmMerchantPickupSession(session.id));
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsMutating(false);
    }
  };

  const forceComplete = async () => {
    if (!session) return;
    const note = forceNote.trim();
    if (!note) {
      setError('La note est obligatoire pour forcer la finalisation.');
      return;
    }

    setIsMutating(true);
    setError(null);
    try {
      setActionResult(await forceCompleteMerchantPickupSession(session.id, note));
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsMutating(false);
    }
  };

  const reset = () => {
    setToken('');
    setSession(null);
    setActionResult(null);
    setForceNote('');
    setError(null);
  };

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Retrait sécurisé</h1>
          <p className="mt-1 text-sm text-muted">
            Colle le token du QR code présenté par le client pour remettre sa Kadhia.
          </p>
        </div>
        {session && (
          <Button variant="ghost" size="md" onClick={reset}>
            Scanner un autre QR
          </Button>
        )}
      </div>

      <section className="mt-5 rounded-md bg-card p-5 shadow-card">
        <label htmlFor="pickup-token" className="text-sm font-black text-ink">
          Token QR de retrait
        </label>
        <div className="mt-2 grid gap-3 md:grid-cols-[1fr_auto]">
          <input
            id="pickup-token"
            type="text"
            value={token}
            onChange={(event) => setToken(event.currentTarget.value)}
            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
            className="min-h-[44px] rounded-md border border-line bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
          />
          <Button
            size="md"
            disabled={!trimmedToken || isScanning}
            onClick={() => void scan()}
          >
            Identifier la Kadhia
          </Button>
        </div>
        <p className="mt-2 text-xs text-muted">
          Le scan caméra sera ajouté plus tard. Cette version accepte un token collé ou saisi.
        </p>
      </section>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      {session && (
        <section className="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
          <div className="rounded-md bg-card shadow-card">
            <div className="border-b border-line p-5">
              <p className="text-xs font-extrabold uppercase text-muted">Session de retrait</p>
              <h2 className="mt-1 text-h2 font-black">Commande {orderLabel}</h2>
              <p className="mt-1 text-sm text-muted">
                Scan effectué à {formatTime(session.scanned_at)} · statut {session.status}
              </p>
            </div>

            <div className="grid gap-4 p-5 md:grid-cols-2">
              <div className="rounded-md border border-line p-4">
                <h3 className="font-black">Client</h3>
                <p className="mt-2 text-sm text-muted">{customerName(session)}</p>
                <p className="mt-1 text-sm text-muted">
                  {session.customer.phone ?? 'Téléphone non renseigné'}
                </p>
              </div>
              <div className="rounded-md border border-line p-4">
                <h3 className="font-black">État retrait</h3>
                <p className="mt-2 text-sm font-bold text-primary">
                  {actionStatusText(actionResult)}
                </p>
                {actionResult?.merchant_confirmed_at && !actionResult.is_completed && (
                  <p className="mt-1 text-sm text-muted">En attente de confirmation client.</p>
                )}
              </div>
            </div>

            <div className="border-t border-line">
              <div className="flex items-center justify-between p-5">
                <h3 className="text-lg font-black">Kadhia</h3>
                <strong>{formatTnd(totalTnd)}</strong>
              </div>
              <div className="divide-y divide-line">
                {session.lines.map((line) => (
                  <div
                    key={line.merchant_product_id}
                    className="grid gap-2 p-5 md:grid-cols-[1fr_auto]"
                  >
                    <div>
                      <strong>{line.name}</strong>
                      <p className="mt-1 text-sm text-muted">
                        {line.quantity} x {formatTnd(line.unit_price_tnd)}
                      </p>
                    </div>
                    <strong>
                      {formatTnd(
                        (Number.parseFloat(line.unit_price_tnd) * line.quantity).toFixed(3),
                      )}
                    </strong>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <aside className="rounded-md bg-card p-5 shadow-card">
            <h3 className="text-lg font-black">Actions</h3>
            <div className="mt-4 space-y-3">
              <Button
                full
                disabled={isMutating || !!actionResult?.merchant_confirmed_at}
                onClick={() => void confirm()}
              >
                Remettre la Kadhia
              </Button>

              {actionResult?.is_completed && (
                <p className="rounded-md bg-status-ready-bg px-3 py-2 text-sm font-bold text-status-ready">
                  Session clôturée.
                </p>
              )}

              {canForceComplete && (
                <div className="rounded-md border border-line p-3">
                  <label htmlFor="force-note" className="text-sm font-black">
                    Note de finalisation forcée
                  </label>
                  <textarea
                    id="force-note"
                    value={forceNote}
                    onChange={(event) => setForceNote(event.currentTarget.value)}
                    maxLength={500}
                    rows={4}
                    className="mt-2 w-full resize-y rounded-md border border-line px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    placeholder="Ex. Client parti sans confirmer sur son téléphone."
                  />
                  <Button
                    full
                    variant="danger"
                    size="md"
                    className="mt-3"
                    disabled={isMutating}
                    onClick={() => void forceComplete()}
                  >
                    Forcer la finalisation
                  </Button>
                </div>
              )}
            </div>
          </aside>
        </section>
      )}
    </div>
  );
}
