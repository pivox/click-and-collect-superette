"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Pagination } from "@/components/ui/Pagination";
import { formatTnd } from "@/lib/format";
import { listOrders } from "@/lib/services";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Order } from "@/types";

const LIMIT = 10;

export default function OrdersListPage() {
  const { user, isLoading } = useClientAuth();
  const [orders, setOrders] = useState<Order[]>([]);
  const [ordersError, setOrdersError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [retryKey, setRetryKey] = useState(0);

  const load = useCallback(async () => {
    if (isLoading || !user) return;
    setOrdersError(null);
    try {
      const result = await listOrders(page, LIMIT);
      setOrders(result.items);
      setPages(Math.ceil(result.total / LIMIT) || 1);
    } catch {
      setOrdersError("Impossible de charger les commandes. Vérifie ta connexion.");
    }
  }, [page, isLoading, user, retryKey]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => { void load(); }, [load]);

  if (isLoading) return null;

  return (
    <>
      <TopBar title="Mes commandes" subtitle="Historique et en cours" backHref="/" />
      {!user ? (
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            <Link href="/login?redirect=/orders" className="font-extrabold text-primary">
              Connecte-toi
            </Link>{" "}
            pour voir tes commandes.
          </p>
        </Card>
      ) : ordersError ? (
        <div className="py-8 text-center">
          <p className="text-sm text-muted">{ordersError}</p>
          <button
            type="button"
            onClick={() => setRetryKey((k) => k + 1)}
            className="mt-3 text-sm font-extrabold text-primary underline"
          >
            Réessayer
          </button>
        </div>
      ) : orders.length === 0 && page === 1 ? (
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">Aucune commande pour le moment.</p>
        </Card>
      ) : (
        <>
          <div className="grid gap-2.5 md:grid-cols-2">
            {orders.map((o) => {
              const badge = orderStatusBadge(o.status);
              return (
                <Link key={o.id} href={`/orders/${o.id}`}>
                  <Card compact className="hover:bg-soft transition-colors">
                    <div className="flex items-baseline justify-between">
                      <strong className="text-sm">{o.code}</strong>
                      <Badge tone={badge.tone}>{badge.label}</Badge>
                    </div>
                    <div className="mt-2 flex items-baseline justify-between text-xs text-muted">
                      <span>{o.shopName ?? "Supérette"}</span>
                      <span className="font-black text-ink">
                        {formatTnd(o.totalAmountTnd)}
                      </span>
                    </div>
                    {o.status === "partially_accepted" && (
                      <div className="mt-2 flex items-center gap-1.5">
                        <span className="inline-block h-2 w-2 rounded-full" style={{ background: "var(--status-wait)" }} />
                        <span className="text-xs font-bold" style={{ color: "var(--status-wait)" }}>
                          Action requise
                        </span>
                      </div>
                    )}
                  </Card>
                </Link>
              );
            })}
          </div>
          <Pagination page={page} pages={pages} onPageChange={setPage} />
        </>
      )}
    </>
  );
}
