'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { OrderStatusBadge } from '@/components/merchant/OrderStatusBadge';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { formatTime, formatTnd } from '@/lib/format';
import { listMerchantOrders } from '@/lib/services/merchant-orders.service';
import type { MerchantOrderList } from '@/lib/types/merchant.types';

const ACTIVE_ORDER_STATUSES = 'submitted,accepted,partially_accepted,preparing,ready,pickup_pending';

export default function MerchantOrdersPage() {
  const { merchant } = useMerchantAuth();
  const [orders, setOrders] = useState<MerchantOrderList | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadOrders = useCallback(async () => {
    if (!merchant) return;
    setIsLoading(true);
    setError(null);
    try {
      setOrders(await listMerchantOrders(merchant.store.id, { status: ACTIVE_ORDER_STATUSES }));
    } catch {
      setError('Impossible de charger les commandes.');
    } finally {
      setIsLoading(false);
    }
  }, [merchant]);

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  return (
    <div>
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Commandes</h1>
          <p className="mt-1 text-sm text-muted">
            Ouvre une commande pour traiter la Kadhia jusqu&apos;à sa préparation.
          </p>
        </div>
        <Button variant="ghost" size="md" onClick={() => void loadOrders()}>
          Réessayer
        </Button>
      </div>

      <div className="mt-5 flex gap-2">
        <span className="rounded-md bg-primary px-3 py-2 text-sm font-bold text-white">Actives</span>
        <span className="rounded-md bg-soft px-3 py-2 text-sm font-bold text-muted">
          Historique à venir
        </span>
      </div>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <section className="mt-5 rounded-md bg-card shadow-card">
        {isLoading ? (
          <p className="p-5 text-sm text-muted">Chargement des commandes…</p>
        ) : orders && orders.items.length > 0 ? (
          <div className="divide-y divide-line">
            {orders.items.map((order) => (
              <Link
                key={order.id}
                href={`/merchant/commandes/${order.id}`}
                aria-label={`Voir la commande ${order.order_number ?? order.id}`}
                className="grid gap-3 p-5 transition hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary md:grid-cols-[1fr_auto]"
              >
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <strong>{order.order_number ?? order.id}</strong>
                    <OrderStatusBadge status={order.status} />
                  </div>
                  <p className="mt-2 text-sm text-muted">
                    {order.line_count} produits
                    {order.pickup_slot?.starts_at
                      ? ` · rendez-vous ${formatTime(order.pickup_slot.starts_at)}`
                      : ''}
                  </p>
                </div>
                <strong className="text-right text-lg">{formatTnd(order.total_tnd)}</strong>
              </Link>
            ))}
          </div>
        ) : (
          <p className="p-5 text-sm text-muted">Aucune commande active pour cette supérette.</p>
        )}
      </section>
    </div>
  );
}
