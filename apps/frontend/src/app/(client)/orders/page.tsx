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

  useEffect(() => {
    if (isLoading || !user) return;
    void listOrders().then(setOrders);
  }, [isLoading, user]);

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
      ) : orders.length === 0 ? (
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">Aucune commande pour le moment.</p>
        </Card>
      ) : (
        <div className="grid gap-2.5 md:grid-cols-2">
          {orders.map((o) => {
            const badge = orderStatusBadge(o.status);
            return (
              <Link key={o.id} href={`/orders/${o.code}`}>
                <Card compact className="hover:bg-soft transition-colors">
                  <div className="flex items-baseline justify-between">
                    <strong className="text-sm">{o.code}</strong>
                    <Badge tone={badge.tone}>{badge.label}</Badge>
                  </div>
                  <div className="mt-2 flex items-baseline justify-between text-xs text-muted">
                    <span>Superette El Amel</span>
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
