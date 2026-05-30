"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { formatTnd } from "@/lib/format";
import { listOrders } from "@/lib/services";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Order } from "@/types";

export default function OrdersListPage() {
  const { user, isLoading } = useClientAuth();
  const [orders, setOrders] = useState<Order[]>([]);
  const [ordersError, setOrdersError] = useState<string | null>(null);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    if (isLoading || !user) return;
    setOrdersError(null);
    void listOrders()
      .then(setOrders)
      .catch(() => setOrdersError("Impossible de charger les commandes. Vérifie ta connexion."));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isLoading, user, retryKey]);

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
      ) : orders.length === 0 ? (
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">Aucune commande pour le moment.</p>
        </Card>
      ) : (
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
                </Card>
              </Link>
            );
          })}
        </div>
      )}
    </>
  );
}
