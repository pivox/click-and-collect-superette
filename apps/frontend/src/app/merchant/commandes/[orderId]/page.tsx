'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import { OrderStatusBadge } from '@/components/merchant/OrderStatusBadge';
import { PartialAcceptDialog } from '@/components/merchant/PartialAcceptDialog';
import { RejectOrderDialog } from '@/components/merchant/RejectOrderDialog';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { formatTime, formatTnd } from '@/lib/format';
import { displayOrderCode } from '@/lib/order-number';
import {
  acceptMerchantOrder,
  getMerchantOrder,
  markMerchantOrderReady,
  partiallyAcceptMerchantOrder,
  rejectMerchantOrder,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';
import type { MerchantOrderDetail } from '@/lib/types/merchant.types';

interface PageProps {
  params: { orderId: string };
}

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

  return "L'action n'a pas pu être effectuée. Recharge la commande puis réessaie.";
}

export default function MerchantOrderDetailPage({ params }: PageProps) {
  const { merchant } = useMerchantAuth();
  const [order, setOrder] = useState<MerchantOrderDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isMutating, setIsMutating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isRejectOpen, setIsRejectOpen] = useState(false);
  const [isPartialOpen, setIsPartialOpen] = useState(false);
  const autoStartedPreparationOrderIds = useRef<Set<string>>(new Set());

  const loadOrder = useCallback(async () => {
    if (!merchant) return;

    setIsLoading(true);
    setError(null);
    try {
      setOrder(await getMerchantOrder(merchant.store.id, params.orderId));
    } catch {
      setError('Impossible de charger cette commande.');
    } finally {
      setIsLoading(false);
    }
  }, [merchant, params.orderId]);

  useEffect(() => {
    void loadOrder();
  }, [loadOrder]);

  useEffect(() => {
    if (!merchant || !order || order.status !== 'accepted') return;
    if (autoStartedPreparationOrderIds.current.has(order.id)) return;

    autoStartedPreparationOrderIds.current.add(order.id);
    const storeId = merchant.store.id;

    const startPreparation = async () => {
      setIsMutating(true);
      setError(null);
      try {
        await startMerchantOrderPreparation(storeId, params.orderId);
        await loadOrder();
      } catch (err) {
        setError(apiErrorMessage(err));
      } finally {
        setIsMutating(false);
      }
    };

    void startPreparation();
  }, [loadOrder, merchant, order, params.orderId]);

  const runAction = async (action: (storeId: string) => Promise<unknown>) => {
    if (!merchant) return;

    const storeId = merchant.store.id;
    setIsMutating(true);
    setError(null);
    try {
      await action(storeId);
      await loadOrder();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsMutating(false);
    }
  };

  const togglePrepared = async (merchantProductId: string, prepared: boolean) => {
    await runAction((storeId) =>
      setMerchantOrderLinePrepared(storeId, params.orderId, merchantProductId, {
        prepared,
      }),
    );
  };

  if (isLoading) {
    return <p className="text-sm text-muted">Chargement de la commande...</p>;
  }

  if (!order) {
    return (
      <div>
        <p className="text-sm text-muted">
          {error ?? 'Commande introuvable pour cette supérette.'}
        </p>
        <Button className="mt-4" variant="ghost" size="md" onClick={() => void loadOrder()}>
          Réessayer
        </Button>
      </div>
    );
  }

  const canMarkReady =
    order.lines.length > 0 && order.lines.every((line) => line.prepared);
  const orderLabel = displayOrderCode(order);

  return (
    <div>
      <Link href="/merchant/commandes" className="text-sm font-bold text-primary">
        Retour aux commandes
      </Link>

      <div className="mt-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-h1 font-black">Commande {orderLabel}</h1>
            <OrderStatusBadge status={order.status} />
          </div>
          <p className="mt-1 text-sm text-muted">
            Rendez-vous{' '}
            {order.pickup_slot
              ? `${formatTime(order.pickup_slot.starts_at)}-${formatTime(order.pickup_slot.ends_at)}`
              : 'non renseigné'}
          </p>
        </div>
        <strong className="text-h2 font-black">{formatTnd(order.total_tnd)}</strong>
      </div>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <section className="mt-5 grid gap-4 md:grid-cols-2">
        <div className="rounded-md bg-card p-5 shadow-card">
          <h2 className="font-black">Client</h2>
          <p className="mt-2 text-sm text-muted">{order.customer_name ?? 'Nom non renseigné'}</p>
          <p className="mt-1 text-sm text-muted">
            {order.customer_phone ?? 'Téléphone non renseigné'}
          </p>
        </div>
        <div className="rounded-md bg-card p-5 shadow-card">
          <h2 className="font-black">Notes client</h2>
          <p className="mt-2 text-sm text-muted">{order.notes ?? 'Aucune note.'}</p>
        </div>
      </section>

      <section className="mt-5 rounded-md bg-card shadow-card">
        <div className="border-b border-line p-5">
          <h2 className="text-lg font-black">Kadhia</h2>
        </div>
        {order.lines.length === 0 ? (
          <p className="p-5 text-sm text-muted">Aucune ligne dans cette Kadhia.</p>
        ) : (
          <div className="divide-y divide-line">
            {order.lines.map((line) => (
              <div key={line.merchant_product_id} className="grid gap-3 p-5 md:grid-cols-[1fr_auto]">
                <div>
                  <strong>{line.product_name ?? line.merchant_product_id}</strong>
                  <p className="mt-1 text-sm text-muted">
                    {line.quantity} x {formatTnd(line.unit_price_tnd)}
                  </p>
                  {order.status === 'preparing' && (
                    <label className="mt-3 flex items-center gap-2 text-sm font-bold">
                      <input
                        type="checkbox"
                        checked={line.prepared}
                        disabled={isMutating}
                        aria-label={`Marquer ${line.product_name ?? line.merchant_product_id} préparé`}
                        onChange={(event) =>
                          void togglePrepared(line.merchant_product_id, event.currentTarget.checked)
                        }
                      />
                      Ligne préparée
                    </label>
                  )}
                </div>
                <strong>{formatTnd(line.line_total_tnd)}</strong>
              </div>
            ))}
          </div>
        )}
      </section>

      <section className="mt-5 rounded-md bg-card p-5 shadow-card">
        <h2 className="text-lg font-black">Actions marchand</h2>
        <div className="mt-4 flex flex-wrap gap-3">
          {order.status === 'submitted' && (
            <>
              <Button
                size="md"
                disabled={isMutating}
                onClick={() =>
                  void runAction((storeId) => acceptMerchantOrder(storeId, params.orderId))
                }
              >
                Accepter
              </Button>
              <Button
                variant="ghost"
                size="md"
                disabled={isMutating}
                onClick={() => setIsRejectOpen(true)}
              >
                Refuser
              </Button>
              <Button
                variant="ghost"
                size="md"
                disabled={isMutating}
                onClick={() => setIsPartialOpen(true)}
              >
                Accepter partiellement
              </Button>
            </>
          )}
          {order.status === 'accepted' && (
            <Button
              size="md"
              disabled={isMutating}
              onClick={() =>
                void runAction((storeId) => startMerchantOrderPreparation(storeId, params.orderId))
              }
            >
              Démarrer préparation
            </Button>
          )}
          {order.status === 'preparing' && (
            <>
              <Button
                size="md"
                disabled={isMutating || !canMarkReady}
                onClick={() =>
                  void runAction((storeId) => markMerchantOrderReady(storeId, params.orderId))
                }
              >
                Commande prête
              </Button>
              {!canMarkReady && (
                <p className="text-sm text-muted">
                  Marque toutes les lignes comme préparées avant de passer la commande prête.
                </p>
              )}
            </>
          )}
          {order.status === 'partially_accepted' && (
            <p className="text-sm text-muted">
              Le client doit ajuster sa Kadhia et la re-soumettre avant la préparation.
            </p>
          )}
          {order.status === 'ready' && (
            <p className="text-sm font-bold text-primary">Commande prête pour le retrait.</p>
          )}
          {order.status === 'rejected' && (
            <p className="text-sm text-status-cancel">
              Commande refusée
              {order.rejection_reason ? ` : ${order.rejection_reason}` : '.'}
            </p>
          )}
          {order.status === 'cancelled' && (
            <p className="text-sm text-muted">Commande annulée.</p>
          )}
        </div>
      </section>

      <RejectOrderDialog
        isOpen={isRejectOpen}
        isSubmitting={isMutating}
        onCancel={() => setIsRejectOpen(false)}
        onConfirm={(reason) => {
          setIsRejectOpen(false);
          void runAction((storeId) => rejectMerchantOrder(storeId, params.orderId, { reason }));
        }}
      />
      <PartialAcceptDialog
        isOpen={isPartialOpen}
        isSubmitting={isMutating}
        lines={order.lines}
        onCancel={() => setIsPartialOpen(false)}
        onConfirm={(payload) => {
          setIsPartialOpen(false);
          void runAction((storeId) =>
            partiallyAcceptMerchantOrder(storeId, params.orderId, payload),
          );
        }}
      />
    </div>
  );
}
