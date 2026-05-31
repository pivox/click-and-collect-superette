'use client';

import { useMemo, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/Button';
import { formatTime, formatTnd } from '@/lib/format';
import { displayOrderCode, fallbackOrderCode } from '@/lib/order-number';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  redeemByCode,
  scanMerchantPickupSession,
  validateManually,
} from '@/lib/services/merchant-pickup.service';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import type {
  MerchantPickupSessionActionResult,
  MerchantPickupSessionForceCompleteResult,
  MerchantPickupSessionScanResult,
  MerchantRedeemByCodeResult,
  MerchantValidateManuallyResult,
} from '@/lib/types/merchant.types';

type Tab = 'qr' | 'code' | 'manual';

const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function apiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const detail = error.response?.data?.detail;
    if (typeof detail === 'string') return detail;
    if (error.response?.status === 404) return 'Code incorrect ou commande non éligible.';
    if (error.response?.status === 409) return 'La commande n\'est pas en état "prête".';
  }
  return "L'action n'a pas pu être effectuée. Réessaie.";
}

const PICKUP_STATUS_LABELS: Record<string, string> = {
  pickup_pending: 'retrait en cours',
  ready: 'prête au retrait',
  completed: 'retrait finalisé',
  cancelled: 'annulée',
  rejected: 'refusée',
};

function pickupStatusLabel(status: string): string {
  return PICKUP_STATUS_LABELS[status] ?? status;
}

function pickupResultText(orderId: string, status: string, manual = false): string {
  const orderLabel = fallbackOrderCode(orderId);

  if (status === 'completed') {
    return manual
      ? `Retrait finalisé manuellement pour la commande ${orderLabel}`
      : `Retrait finalisé pour la commande ${orderLabel}`;
  }

  return `Commande ${orderLabel} — ${pickupStatusLabel(status)}`;
}

function lineTotalMillimes(line: MerchantPickupSessionScanResult['lines'][number]): number {
  return Math.round(Number.parseFloat(line.unit_price_tnd) * 1000) * line.quantity;
}

function lineTotalTnd(line: MerchantPickupSessionScanResult['lines'][number]): string {
  return (lineTotalMillimes(line) / 1000).toFixed(3);
}

function customerName(session: MerchantPickupSessionScanResult): string {
  const displayName = session.customer.display_name?.trim();
  if (displayName) return displayName;

  const splitName = [session.customer.first_name, session.customer.last_name]
    .filter(Boolean)
    .join(' ')
    .trim();

  return splitName || 'Client non renseigné';
}

function actionStatusText(
  action: MerchantPickupSessionActionResult | MerchantPickupSessionForceCompleteResult | null,
): string {
  if (!action) return 'QR scanné. Retrait en attente de confirmation marchand.';
  if (action.is_completed) return 'Retrait finalisé.';
  if (action.merchant_confirmed_at) return 'Confirmation marchand enregistrée.';
  return 'Retrait en attente.';
}

const TABS: { id: Tab; label: string }[] = [
  { id: 'qr', label: 'QR Code' },
  { id: 'code', label: 'Code 4 chiffres' },
  { id: 'manual', label: 'Manuel' },
];

export default function MerchantPickupPage() {
  const { merchant } = useMerchantAuth();
  const storeId = merchant?.store?.id ?? '';

  // QR tab state
  const [token, setToken] = useState('');
  const [session, setSession] = useState<MerchantPickupSessionScanResult | null>(null);
  const [actionResult, setActionResult] = useState<
    MerchantPickupSessionActionResult | MerchantPickupSessionForceCompleteResult | null
  >(null);
  const [forceNote, setForceNote] = useState('');
  const [isScanning, setIsScanning] = useState(false);

  // Code tab state
  const [pickupCode, setPickupCode] = useState('');
  const [codeResult, setCodeResult] = useState<MerchantRedeemByCodeResult | null>(null);
  const [isRedeemingCode, setIsRedeemingCode] = useState(false);

  // Manual tab state
  const [manualOrderId, setManualOrderId] = useState('');
  const [manualNote, setManualNote] = useState('');
  const [manualResult, setManualResult] = useState<MerchantValidateManuallyResult | null>(null);
  const [isValidatingManually, setIsValidatingManually] = useState(false);

  // Shared state
  const [activeTab, setActiveTab] = useState<Tab>('qr');
  const [error, setError] = useState<string | null>(null);
  const [isMutating, setIsMutating] = useState(false);

  const trimmedToken = token.trim();
  const orderLabel = session ? displayOrderCode({ ...session, id: session.order_id }) : '';
  const canForceComplete =
    !!actionResult?.merchant_confirmed_at &&
    !actionResult.customer_confirmed_at &&
    !actionResult.is_completed;
  const totalTnd = useMemo(() => {
    if (!session) return '0.000';
    const total = session.lines.reduce((sum, line) => sum + lineTotalMillimes(line), 0);
    return (total / 1000).toFixed(3);
  }, [session]);

  // QR actions
  const scan = async () => {
    setError(null);
    if (!UUID_PATTERN.test(trimmedToken)) {
      setError('Le token QR doit être un UUID valide.');
      return;
    }
    setIsScanning(true);
    try {
      setSession(await scanMerchantPickupSession(trimmedToken));
      setActionResult(null);
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

  const resetQr = () => {
    setToken('');
    setSession(null);
    setActionResult(null);
    setForceNote('');
    setError(null);
  };

  // Code action
  const handleRedeemCode = async () => {
    setError(null);
    const code = pickupCode.trim();
    if (!/^\d{4}$/.test(code)) {
      setError('Le code doit être composé de 4 chiffres.');
      return;
    }
    if (!storeId) {
      setError('Supérette non identifiée. Reconnecte-toi.');
      return;
    }
    setIsRedeemingCode(true);
    try {
      const result = await redeemByCode(storeId, code);
      setCodeResult(result);
      setPickupCode('');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsRedeemingCode(false);
    }
  };

  // Manual action
  const handleValidateManually = async () => {
    setError(null);
    const orderId = manualOrderId.trim();
    const note = manualNote.trim();
    if (!UUID_PATTERN.test(orderId)) {
      setError("L'identifiant de commande doit être un UUID valide.");
      return;
    }
    if (note.length < 5) {
      setError('La note est obligatoire (5 caractères minimum).');
      return;
    }
    if (!storeId) {
      setError('Supérette non identifiée. Reconnecte-toi.');
      return;
    }
    setIsValidatingManually(true);
    try {
      const result = await validateManually(storeId, orderId, note);
      setManualResult(result);
      setManualOrderId('');
      setManualNote('');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsValidatingManually(false);
    }
  };

  const changeTab = (tab: Tab) => {
    setActiveTab(tab);
    setError(null);
  };

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Retrait sécurisé</h1>
          <p className="mt-1 text-sm text-muted">
            Demande au client son QR code ou son code de retrait à 4 chiffres.
          </p>
        </div>
      </div>

      {/* Onglets */}
      <div className="mt-5 flex border-b border-line">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => changeTab(tab.id)}
            className={[
              'px-4 py-2 text-sm font-bold transition-colors',
              activeTab === tab.id
                ? 'border-b-2 border-primary text-primary'
                : 'text-muted hover:text-ink',
            ].join(' ')}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {error && (
        <div
          role="alert"
          className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel"
        >
          {error}
        </div>
      )}

      {/* Onglet QR */}
      {activeTab === 'qr' && (
        <div className="mt-5">
          <div className="flex items-center justify-between">
            <p className="text-sm text-muted">
              Colle le token du QR code présenté par le client.
            </p>
            {session && (
              <Button variant="ghost" size="md" onClick={resetQr} disabled={isMutating}>
                Scanner un autre QR
              </Button>
            )}
          </div>

          <section className="mt-3 rounded-md bg-card p-5 shadow-card">
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
                disabled={!trimmedToken || isScanning || isMutating}
                onClick={() => void scan()}
              >
                Identifier la Kadhia
              </Button>
            </div>
          </section>

          {session && (
            <section className="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
              <div className="rounded-md bg-card shadow-card">
                <div className="border-b border-line p-5">
                  <p className="text-xs font-extrabold uppercase text-muted">Session de retrait</p>
                  <h2 className="mt-1 text-h2 font-black">Commande {orderLabel}</h2>
                  <p className="mt-1 text-sm text-muted">
                    Scan à {formatTime(session.scanned_at)} · statut{' '}
                    {pickupStatusLabel(session.status)}
                  </p>
                </div>
                <div className="grid gap-4 p-5 md:grid-cols-2">
                  <div className="rounded-md border border-line p-4">
                    <h3 className="font-black">Client</h3>
                    <p className="mt-2 text-sm text-muted">{customerName(session)}</p>
                    <p className="mt-1 text-sm text-muted">{session.customer.phone ?? '—'}</p>
                  </div>
                  <div className="rounded-md border border-line p-4">
                    <h3 className="font-black">État</h3>
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
                        <strong>{formatTnd(lineTotalTnd(line))}</strong>
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
      )}

      {/* Onglet Code 4 chiffres */}
      {activeTab === 'code' && (
        <div className="mt-5">
          <p className="text-sm text-muted">
            Le client te communique verbalement son code à 4 chiffres.
          </p>

          {codeResult ? (
            <div className="mt-4 rounded-md bg-status-ready-bg px-4 py-4 text-center">
              <p className="text-lg font-black text-status-ready">Retrait validé ✓</p>
              <p className="mt-1 text-sm text-muted">
                {pickupResultText(codeResult.order_id, codeResult.status)}
              </p>
              <Button
                variant="ghost"
                size="md"
                className="mt-3"
                onClick={() => {
                  setCodeResult(null);
                  setError(null);
                }}
              >
                Valider une autre commande
              </Button>
            </div>
          ) : (
            <section className="mt-3 rounded-md bg-card p-5 shadow-card">
              <label htmlFor="pickup-code" className="text-sm font-black text-ink">
                Code de retrait (4 chiffres)
              </label>
              <div className="mt-2 grid gap-3 md:grid-cols-[1fr_auto]">
                <input
                  id="pickup-code"
                  type="text"
                  inputMode="numeric"
                  pattern="\d{4}"
                  maxLength={4}
                  value={pickupCode}
                  onChange={(event) =>
                    setPickupCode(event.currentTarget.value.replace(/\D/g, ''))
                  }
                  placeholder="1234"
                  className="min-h-[44px] rounded-md border border-line bg-white px-3 text-center text-xl font-black tracking-[0.5em] outline-none focus:ring-2 focus:ring-primary"
                />
                <Button
                  size="md"
                  disabled={pickupCode.length !== 4 || isRedeemingCode}
                  onClick={() => void handleRedeemCode()}
                >
                  Valider
                </Button>
              </div>
            </section>
          )}
        </div>
      )}

      {/* Onglet Manuel */}
      {activeTab === 'manual' && (
        <div className="mt-5">
          <p className="text-sm text-muted">
            Utilise ce mode si ni le QR code ni le code 4 chiffres ne fonctionnent. Une note est
            obligatoire pour l&apos;audit.
          </p>

          {manualResult ? (
            <div className="mt-4 rounded-md bg-status-ready-bg px-4 py-4 text-center">
              <p className="text-lg font-black text-status-ready">
                Retrait validé manuellement ✓
              </p>
              <p className="mt-1 text-sm text-muted">
                {pickupResultText(manualResult.order_id, manualResult.status, true)}
              </p>
              <Button
                variant="ghost"
                size="md"
                className="mt-3"
                onClick={() => {
                  setManualResult(null);
                  setError(null);
                }}
              >
                Valider une autre commande
              </Button>
            </div>
          ) : (
            <section className="mt-3 rounded-md bg-card p-5 shadow-card space-y-4">
              <div>
                <label htmlFor="manual-order-id" className="text-sm font-black text-ink">
                  Identifiant de commande (UUID)
                </label>
                <input
                  id="manual-order-id"
                  type="text"
                  value={manualOrderId}
                  onChange={(event) => setManualOrderId(event.currentTarget.value)}
                  placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                  className="mt-2 w-full min-h-[44px] rounded-md border border-line bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                />
              </div>
              <div>
                <label htmlFor="manual-note" className="text-sm font-black text-ink">
                  Motif (obligatoire, 5 caractères minimum)
                </label>
                <textarea
                  id="manual-note"
                  value={manualNote}
                  onChange={(event) => setManualNote(event.currentTarget.value)}
                  maxLength={500}
                  rows={3}
                  className="mt-2 w-full resize-y rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                  placeholder="Ex. Client présent, QR inaccessible, caméra défaillante."
                />
              </div>
              <Button
                full
                disabled={isValidatingManually}
                onClick={() => void handleValidateManually()}
              >
                Valider manuellement
              </Button>
            </section>
          )}
        </div>
      )}
    </div>
  );
}
