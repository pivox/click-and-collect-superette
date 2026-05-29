"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { Timeline } from "@/components/ui/Timeline";
import { Button } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { getOrder, projectTimeline } from "@/lib/services";
import { formatTnd, formatSlotDate } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Order } from "@/types";

export default function OrderTrackingPage({
  params,
}: {
  params: { orderId: string };
}) {
  const { orderId } = params;
  const { user, isLoading: authLoading } = useClientAuth();
  const [order, setOrder] = useState<Order | null>(null);
  const [fetchDone, setFetchDone] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    if (authLoading || !user) return;
    void getOrder(orderId)
      .then((data) => { setOrder(data); setFetchDone(true); })
      .catch(() => setFetchDone(true));
  }, [orderId, user, authLoading]);

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      const fresh = await getOrder(orderId);
      if (fresh) setOrder(fresh);
    } finally {
      setRefreshing(false);
    }
  };

  if (authLoading) return null;

  if (!user) {
    return (
      <>
        <TopBar title="Suivi commande" backHref="/orders" />
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            <Link
              href={`/login?redirect=/orders/${orderId}`}
              className="font-extrabold text-primary"
            >
              Connecte-toi
            </Link>{" "}
            pour consulter ta commande.
          </p>
        </Card>
      </>
    );
  }

  if (!fetchDone) return null;
  if (!order) notFound();

  const badge = orderStatusBadge(order.status);
  const steps = projectTimeline(order);
  const showQrCta = order.status === "ready" || order.status === "pickup_pending";

  return (
    <>
      <TopBar
        title={order.code}
        subtitle="Superette El Amel"
        backHref="/orders"
      />

      <div className="md:grid md:grid-cols-2 md:gap-5 md:items-start">
        {/* Colonne gauche : timeline */}
        <div>
          <Card>
            <Badge tone={badge.tone}>{badge.label}</Badge>
            <div className="mt-3">
              <Summary>
                <SummaryRow
                  label="Retrait"
                  value={order.pickupSlot ? formatSlotDate(order.pickupSlot.startsAt) : "—"}
                />
                <SummaryRow
                  label="Total"
                  value={formatTnd(order.totalAmountTnd)}
                />
                <SummaryRow label="Code" value={order.code} />
              </Summary>
            </div>
          </Card>

          <section className="mt-4">
            <div className="mb-2.5 flex items-center justify-between">
              <h3 className="text-h3 font-extrabold">Suivi</h3>
              <button
                type="button"
                onClick={handleRefresh}
                disabled={refreshing}
                className="text-xs font-extrabold text-primary underline disabled:opacity-50"
              >
                {refreshing ? "Actualisation…" : "Actualiser"}
              </button>
            </div>
            <Card>
              <Timeline steps={steps} />
            </Card>
          </section>
        </div>

        {/* Colonne droite : note + CTA */}
        <div>
          {order.customerNote && (
            <section className="mt-4 md:mt-0">
              <h3 className="mb-2.5 text-h3 font-extrabold">Ta note</h3>
              <Card className="text-sm text-muted">{order.customerNote}</Card>
            </section>
          )}

          {order.status === "ready" && order.pickupCode && (
            <section className="mt-4">
              <h3 className="mb-2.5 text-h3 font-extrabold">Code de retrait</h3>
              <Card>
                <div className="flex flex-col items-center py-3 gap-3">
                  <div className="flex gap-2">
                    {order.pickupCode.split("").map((digit, i) => (
                      <span
                        key={i}
                        className="flex h-12 w-10 items-center justify-center rounded-md border-2 border-primary text-2xl font-black text-primary"
                      >
                        {digit}
                      </span>
                    ))}
                  </div>
                  <p className="text-center text-xs text-muted">
                    Communique ce code au marchand si le QR code ne peut pas
                    être scanné.
                  </p>
                </div>
              </Card>
            </section>
          )}

          {/* CTA inline sur desktop */}
          <div className="hidden md:block mt-4">
            {showQrCta ? (
              <Link href={`/orders/${order.id}/pickup`}>
                <Button full>Afficher le QR retrait</Button>
              </Link>
            ) : (
              <Button full disabled>
                QR retrait — disponible quand prête
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* CTA sticky sur mobile */}
      <StickyBottom className="md:hidden">
        {showQrCta ? (
          <Link href={`/orders/${order.id}/pickup`}>
            <Button full>Afficher le QR retrait</Button>
          </Link>
        ) : (
          <Button full disabled>
            QR retrait — disponible quand la commande est prête
          </Button>
        )}
      </StickyBottom>
    </>
  );
}
