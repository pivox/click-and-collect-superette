'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import { OrderStatusBadge } from '@/components/merchant/OrderStatusBadge';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { cn } from '@/lib/cn';
import { formatTime, formatTnd } from '@/lib/format';
import {
  listMerchantOrderHistory,
  listMerchantOrders,
} from '@/lib/services/merchant-orders.service';
import type {
  MerchantOrderHistoryItem,
  MerchantOrderHistoryList,
  MerchantOrderList,
} from '@/lib/types/merchant.types';

const ACTIVE_ORDER_STATUSES = 'submitted,accepted,partially_accepted,preparing,ready,pickup_pending';
const HISTORY_PICKUP_STATUSES = 'ready,pickup_pending';
const HISTORY_CLOSED_STATUSES = 'completed,cancelled,rejected';
const ORDERS_PAGE_LIMIT = 20;

type OrdersTab = 'active' | 'history';
type HistoryFilter = 'pickup' | 'closed';

function historyCustomerName(order: MerchantOrderHistoryItem): string {
  const name = [order.customer.first_name, order.customer.last_name]
    .filter(Boolean)
    .join(' ')
    .trim();

  return name || 'Client non renseigné';
}

function historyStatusForFilter(filter: HistoryFilter): string {
  return filter === 'pickup' ? HISTORY_PICKUP_STATUSES : HISTORY_CLOSED_STATUSES;
}

function HistoryPagination({
  page,
  limit,
  total,
  onPageChange,
}: {
  page: number;
  limit: number;
  total: number;
  onPageChange: (page: number) => void;
}) {
  const totalPages = Math.max(1, Math.ceil(total / limit));

  if (totalPages <= 1) {
    return null;
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
      <p className="text-sm text-muted">
        Page {page} sur {totalPages}
      </p>
      <div className="flex gap-2">
        <Button
          variant="ghost"
          size="md"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
        >
          Page précédente
        </Button>
        <Button
          variant="ghost"
          size="md"
          disabled={page >= totalPages}
          onClick={() => onPageChange(page + 1)}
        >
          Page suivante
        </Button>
      </div>
    </div>
  );
}

export default function MerchantOrdersPage() {
  const { merchant } = useMerchantAuth();
  const [orders, setOrders] = useState<MerchantOrderList | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedTab, setSelectedTab] = useState<OrdersTab>('active');
  const [historyFilter, setHistoryFilter] = useState<HistoryFilter>('pickup');
  const [historyPage, setHistoryPage] = useState(1);
  const [historyOrders, setHistoryOrders] = useState<MerchantOrderHistoryList | null>(null);
  const [isHistoryLoading, setIsHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);
  const historyRequestId = useRef(0);

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

  const loadHistoryOrders = useCallback(async () => {
    if (!merchant) return;
    const requestId = historyRequestId.current + 1;
    historyRequestId.current = requestId;
    setIsHistoryLoading(true);
    setHistoryError(null);
    try {
      const nextHistoryOrders = await listMerchantOrderHistory(merchant.store.id, {
        page: historyPage,
        limit: ORDERS_PAGE_LIMIT,
        status: historyStatusForFilter(historyFilter),
      });
      if (historyRequestId.current === requestId) {
        setHistoryOrders(nextHistoryOrders);
      }
    } catch {
      if (historyRequestId.current === requestId) {
        setHistoryOrders(null);
        setHistoryError("Impossible de charger l'historique des commandes.");
      }
    } finally {
      if (historyRequestId.current === requestId) {
        setIsHistoryLoading(false);
      }
    }
  }, [historyFilter, historyPage, merchant]);

  const switchTab = (tab: OrdersTab) => {
    setSelectedTab(tab);
    setHistoryPage(1);
  };

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  useEffect(() => {
    if (selectedTab === 'history') {
      void loadHistoryOrders();
    }
  }, [loadHistoryOrders, selectedTab]);

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

      <div role="tablist" aria-label="Commandes marchand" className="mt-5 flex gap-2">
        <button
          type="button"
          role="tab"
          aria-selected={selectedTab === 'active'}
          aria-controls="panel-active-orders"
          className={cn(
            'rounded-md px-3 py-2 text-sm font-bold transition-colors',
            selectedTab === 'active' ? 'bg-primary text-white' : 'bg-soft text-muted',
          )}
          onClick={() => switchTab('active')}
        >
          Actives
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={selectedTab === 'history'}
          aria-controls="panel-history-orders"
          className={cn(
            'rounded-md px-3 py-2 text-sm font-bold transition-colors',
            selectedTab === 'history' ? 'bg-primary text-white' : 'bg-soft text-muted',
          )}
          onClick={() => switchTab('history')}
        >
          Historique
        </button>
      </div>

      {selectedTab === 'active' && error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      {selectedTab === 'active' && (
        <section id="panel-active-orders" role="tabpanel" className="mt-5 rounded-md bg-card shadow-card">
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
      )}

      {selectedTab === 'history' && (
        <section id="panel-history-orders" role="tabpanel" className="mt-5 rounded-md bg-card shadow-card">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4">
            <div role="tablist" aria-label="Filtres historique commandes" className="flex gap-2">
              <button
                type="button"
                role="tab"
                aria-selected={historyFilter === 'pickup'}
                className={cn(
                  'rounded-md px-3 py-2 text-sm font-bold transition-colors',
                  historyFilter === 'pickup' ? 'bg-primary text-white' : 'bg-soft text-muted',
                )}
                onClick={() => {
                  setHistoryFilter('pickup');
                  setHistoryPage(1);
                }}
              >
                À retirer
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={historyFilter === 'closed'}
                className={cn(
                  'rounded-md px-3 py-2 text-sm font-bold transition-colors',
                  historyFilter === 'closed' ? 'bg-primary text-white' : 'bg-soft text-muted',
                )}
                onClick={() => {
                  setHistoryFilter('closed');
                  setHistoryPage(1);
                }}
              >
                Clôturées
              </button>
            </div>
          </div>

          {historyError && (
            <div className="m-4 flex flex-wrap items-center justify-between gap-3 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
              <span>{historyError}</span>
              <Button
                variant="ghost"
                size="md"
                disabled={isHistoryLoading}
                onClick={() => void loadHistoryOrders()}
              >
                Réessayer
              </Button>
            </div>
          )}

          {isHistoryLoading ? (
            <p className="p-5 text-sm text-muted">Chargement de l&apos;historique...</p>
          ) : historyOrders && historyOrders.items.length > 0 ? (
            <>
              <div className="divide-y divide-line">
                {historyOrders.items.map((order) => (
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
                        <span>{historyCustomerName(order)}</span>
                        {order.pickup_slot?.starts_at
                          ? ` · rendez-vous ${formatTime(order.pickup_slot.starts_at)}`
                          : ''}
                        {' · '}
                        mis à jour {formatTime(order.updated_at)}
                      </p>
                    </div>
                    <strong className="text-right text-lg">{formatTnd(order.total)}</strong>
                  </Link>
                ))}
              </div>
              <HistoryPagination
                page={historyOrders.page}
                limit={historyOrders.limit}
                total={historyOrders.total}
                onPageChange={setHistoryPage}
              />
            </>
          ) : (
            <p className="p-5 text-sm text-muted">Aucune commande dans cet historique.</p>
          )}
        </section>
      )}
    </div>
  );
}
